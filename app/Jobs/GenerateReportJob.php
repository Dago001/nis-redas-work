<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\GeneratedReport;
use App\Models\ReportIndicatorValue;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AuditLogger;
use App\Services\ExcelReportService;
use App\Services\ReportAccess;
use App\Support\CsvWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        public readonly int $generatedReportId
    ) {}

    public function handle(ExcelReportService $excel): void
    {
        /** @var GeneratedReport|null $report */
        $report = GeneratedReport::query()->find($this->generatedReportId);
        if (! $report) {
            return;
        }

        /** @var User|null $user */
        $user = User::query()->find($report->user_id);
        if (! $user) {
            $report->update(['status' => 'failed', 'error' => 'Requesting user no longer exists.']);

            return;
        }

        $report->update(['status' => 'processing', 'error' => null]);

        $filters = is_array($report->filters) ? $report->filters : [];
        $dateFrom = $filters['date_from'] ?? now()->startOfYear()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();
        $sections = array_values(array_filter((array) ($filters['sections'] ?? []), 'is_string'));
        $scopeLabel = is_string($filters['scope_label'] ?? null) ? trim($filters['scope_label']) : '';
        $statusFilter = is_string($filters['status_filter'] ?? null) ? strtolower(trim($filters['status_filter'])) : 'all';

        $query = ReportAccess::applyVisibility(
            Application::query()->with('user:id,name,service_number'),
            $user
        )
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if (in_array($statusFilter, ['approved', 'pending', 'returned'], true)) {
            $query->where('status', $statusFilter);
        }

        if (! empty($filters['command_code'])) {
            $query->where('scope_code', strtoupper((string) $filters['command_code']));
        } elseif (! empty($filters['directorate_code'])) {
            $query->where('category', 'directorate')
                ->where('scope_code', strtolower((string) $filters['directorate_code']));
        } elseif (! empty($filters['cgis_unit_code'])) {
            $query->where('category', 'cgis_unit')
                ->where('scope_code', strtolower((string) $filters['cgis_unit_code']));
        } elseif (! empty($filters['zone_code'])) {
            $zoneCode = strtoupper((string) $filters['zone_code']);
            $zoneCommands = array_keys((array) config("nis_hierarchy.zones.{$zoneCode}.commands", []));
            $dbFormations = ReportAccess::formationCodesForZone($zoneCode);
            $allZoneCodes = array_values(array_unique(array_merge([$zoneCode], $zoneCommands, $dbFormations)));
            $query->whereIn('scope_code', $allZoneCodes);
        }

        $applications = $query->orderBy('created_at')->get();

        // Filter by return_type in PHP for SQLite + MySQL portability
        $returnTypeMap = [
            'monthly_return' => 'monthly',
            'quarterly_return' => 'quarterly',
            'annual_return' => 'annual',
        ];
        if (isset($returnTypeMap[$report->report_type])) {
            $expectedType = $returnTypeMap[$report->report_type];
            $applications = $applications->filter(function (Application $app) use ($expectedType): bool {
                $rt = strtolower((string) ($app->return_data['return_type'] ?? 'monthly'));

                return $rt === $expectedType;
            })->values();
        }

        [$headers, $rows, $title] = $this->buildDataset($report->report_type, $applications, $sections, $scopeLabel);

        $disk = Storage::disk('local');
        $ext = match ($report->format) {
            'excel' => 'xlsx',
            'csv' => 'csv',
            default => 'pdf',
        };
        $storedPath = "reports/{$report->uuid}.{$ext}";

        if ($report->format === 'csv') {
            $tmp = tempnam(sys_get_temp_dir(), 'redas-csv-');
            $fh = fopen($tmp, 'wb');
            fwrite($fh, "\xEF\xBB\xBF");
            CsvWriter::writeRow($fh, $headers);
            foreach ($rows as $row) {
                CsvWriter::writeRow($fh, $row);
            }
            fclose($fh);
            $disk->put($storedPath, file_get_contents($tmp));
            @unlink($tmp);
        } elseif ($report->format === 'excel') {
            $tmpXlsx = $excel->writeSheet($title, $headers, $rows, 'REDAS Report');
            $disk->put($storedPath, file_get_contents($tmpXlsx));
            @unlink($tmpXlsx);
        } else {
            $truncated = count($rows) > 2000;
            $pdfRows = $truncated ? array_slice($rows, 0, 2000) : $rows;
            $pdf = Pdf::loadView('pdf.generated-report', [
                'title' => $title,
                'report' => $report,
                'user' => $user,
                'headers' => $headers,
                'rows' => $pdfRows,
                'totalRows' => count($rows),
                'truncated' => $truncated,
                'filters' => $filters,
            ])->setPaper('a4', 'landscape');
            $disk->put($storedPath, $pdf->output());
        }

        $report->update([
            'status' => 'completed',
            'stored_path' => $storedPath,
            'row_count' => count($rows),
            'completed_at' => now(),
        ]);

        UserNotification::create([
            'user_id' => $user->id,
            'type' => 'info',
            'title' => 'Your report is ready for download',
            'description' => "{$title} ({$report->format}) completed with ".count($rows).' row(s).',
            'tag' => 'report',
            'action_url' => route('user.reports.download', $report),
            'payload_json' => ['generated_report_id' => $report->id, 'uuid' => $report->uuid],
        ]);

        AuditLogger::log(
            user: $user,
            action: 'report.generated',
            status: 'success',
            entityType: 'generated_report',
            entityId: $report->id,
            details: [
                'report_type' => $report->report_type,
                'format' => $report->format,
                'row_count' => count($rows),
            ]
        );
    }

    public function failed(Throwable $e): void
    {
        Log::error('Report generation job failed', [
            'generated_report_id' => $this->generatedReportId,
            'error' => $e->getMessage(),
        ]);

        GeneratedReport::query()
            ->whereKey($this->generatedReportId)
            ->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500),
            ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Application>  $applications
     * @param  list<string>  $sections
     * @return array{0:list<string>,1:list<list<mixed>>,2:string}
     */
    private function buildDataset(string $reportType, $applications, array $sections, string $scopeLabel = ''): array
    {
        $suffix = $scopeLabel !== '' ? " - {$scopeLabel}" : '';

        if ($reportType === 'consolidated_summary') {
            $headers = ['Scope / Command', 'Category', 'Total Returns', 'Approved', 'Pending', 'Returned', 'Latest Period'];
            $rows = [];

            foreach ($applications->groupBy(fn (Application $a) => ($a->scope_code ?? 'UNKNOWN').'|'.($a->category ?? 'state')) as $key => $group) {
                [$scope, $cat] = explode('|', $key, 2);
                $rows[] = [
                    $scope,
                    ucfirst($cat),
                    $group->count(),
                    $group->where('status', 'approved')->count(),
                    $group->where('status', 'pending')->count(),
                    $group->where('status', 'returned')->count(),
                    (string) ($group->max('report_period') ?? '-'),
                ];
            }

            return [$headers, $rows, 'NIS REDAS National Consolidated Summary'.$suffix];
        }

        if ($reportType === 'compliance_report') {
            $headers = ['Scope', 'Category', 'Total', 'Approved', 'Pending', 'Returned'];
            $rows = [];

            foreach ($applications->groupBy(fn (Application $a) => ($a->scope_code ?? 'UNKNOWN').'|'.($a->category ?? 'state')) as $key => $group) {
                [$scope, $cat] = explode('|', $key, 2);
                $rows[] = [
                    $scope,
                    ucfirst($cat),
                    $group->count(),
                    $group->where('status', 'approved')->count(),
                    $group->where('status', 'pending')->count(),
                    $group->where('status', 'returned')->count(),
                ];
            }

            return [$headers, $rows, 'NIS REDAS Compliance Report'.$suffix];
        }

        if ($reportType === 'full_export') {
            $headers = ['Ref', 'Scope', 'Category', 'Period', 'Section', 'Indicator Code', 'Indicator Name', 'Value', 'Status'];
            $rows = [];
            $appIds = $applications->pluck('id')->all();
            $appsById = $applications->keyBy('id');

            $values = $appIds === []
                ? collect()
                : ReportIndicatorValue::query()
                    ->with('indicator:id,code,name,section,status')
                    ->whereIn('application_id', $appIds)
                    ->where('is_current', true)
                    ->get();

            foreach ($values as $iv) {
                $ind = $iv->indicator;
                if (! $ind || $ind->status === 'excluded') {
                    continue;
                }
                if ($sections !== [] && $ind->section !== null && ! in_array($ind->section, $sections, true)) {
                    continue;
                }
                /** @var Application|null $app */
                $app = $appsById->get($iv->application_id);
                $rows[] = [
                    'RET-'.str_pad((string) $iv->application_id, 5, '0', STR_PAD_LEFT),
                    $app?->scope_code ?? '',
                    ucfirst((string) ($app?->category ?? '')),
                    $app?->report_period ?? '',
                    $ind->section ?? '',
                    $ind->code,
                    $ind->name,
                    (float) $iv->value,
                    $app?->status ?? '',
                ];
            }

            return [$headers, $rows, 'NIS REDAS Full Indicator Data Export'.$suffix];
        }

        $headers = ['Ref', 'Formation/Scope', 'Category', 'Period', 'Officer', 'Status', 'Stage', 'Version', 'Submitted At'];
        $rows = [];
        foreach ($applications as $app) {
            $rows[] = [
                'RET-'.str_pad((string) $app->id, 5, '0', STR_PAD_LEFT),
                (string) ($app->return_data['command_name'] ?? $app->scope_code ?? ''),
                ucfirst((string) ($app->category ?? '')),
                (string) ($app->report_period ?? $app->return_data['report_period'] ?? ''),
                (string) ($app->return_data['reporting_officer'] ?? $app->user?->name ?? ''),
                (string) ($app->status ?? ''),
                (string) ($app->workflow_stage ?? ''),
                (int) max(1, (int) $app->current_version),
                (string) optional($app->created_at)->toDateTimeString(),
            ];
        }

        $title = 'NIS REDAS '.ucwords(str_replace('_', ' ', $reportType)).$suffix;

        return [$headers, $rows, $title];
    }
}
