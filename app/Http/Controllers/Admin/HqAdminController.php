<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ExcelReportService;
use App\Services\HqAnalyticsService;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HqAdminController extends Controller
{
    private const DIRECTORATES = [
        'hrm' => 'Human Resources Management (HRM)',
        'prs' => 'Planning, Research and Statistics (PRS)',
        'finance' => 'Finance and Accounts (F/A)',
        'investigation' => 'Investigation and Compliance (I/C)',
        'passport' => 'Passport and Other Travel Documents (P/OTD)',
        'visa' => 'Visa and Residency (V/R)',
        'migration' => 'Migration Directorate',
        'border' => 'Border Management',
        'ict' => 'ICT/Cybersecurity Directorate',
        'works-logistics' => 'Works and Logistics',
    ];

    private const STAGE_LABELS = [
        SubmissionWorkflow::STAGE_SUBMITTED => 'Submitted',
        SubmissionWorkflow::STAGE_DESK_REVIEW => 'Desk Admin Review',
        SubmissionWorkflow::STAGE_ZONAL_REVIEW => 'Zonal Review',
        SubmissionWorkflow::STAGE_DIRECTORATE_REVIEW => 'Directorate Admin Review',
        SubmissionWorkflow::STAGE_HQ_REVIEW => 'HQ Admin Review',
        SubmissionWorkflow::STAGE_ADMIN_REVIEW => 'Final Approval',
        SubmissionWorkflow::STAGE_APPROVED => 'Approved',
    ];

    public function index(): View
    {
        $base = Application::query();

        $stats = [
            'total' => (clone $base)->count(),
            'awaiting_hq' => (clone $base)->awaiting(SubmissionWorkflow::STAGE_HQ_REVIEW)->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'returned' => (clone $base)->where('status', 'returned')->count(),
        ];

        $directorateRows = Application::query()
            ->selectRaw("scope_code, status, count(*) as aggregate")
            ->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
            ->groupBy('scope_code', 'status')
            ->get();

        $directorates = collect(self::DIRECTORATES)->map(function (string $name, string $slug) use ($directorateRows) {
            $rows = $directorateRows->where('scope_code', $slug);

            return [
                'slug' => $slug,
                'name' => $name,
                'total' => $rows->sum('aggregate'),
                'pending' => $rows->where('status', 'pending')->sum('aggregate'),
                'approved' => $rows->where('status', 'approved')->sum('aggregate'),
                'returned' => $rows->where('status', 'returned')->sum('aggregate'),
            ];
        })->values();

        $cgisUnits = Application::query()
            ->with('user:id,assigned_cgis_unit_code')
            ->whereHas('user', fn ($q) => $q->whereNotNull('assigned_cgis_unit_code')->where('assigned_cgis_unit_code', '!=', ''))
            ->get()
            ->groupBy(fn (Application $application) => $application->user->assigned_cgis_unit_code)
            ->map(fn (Collection $group, string $unit) => [
                'unit' => $unit,
                'total' => $group->count(),
                'approved' => $group->where('status', 'approved')->count(),
            ])
            ->sortBy('unit')
            ->values();

        $recentReturns = Application::query()
            ->with('user:id,name,service_number,assigned_cgis_unit_code')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.headquarters.index', [
            'stats' => $stats,
            'directorates' => $directorates,
            'cgisUnits' => $cgisUnits,
            'recentReturns' => $recentReturns,
        ]);
    }

    public function returns(Request $request): View
    {
        $filters = $request->validate([
            'formation' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'in:state,directorate,cgis'],
            'status' => ['nullable', 'in:pending,approved,returned'],
            'stage' => ['nullable', 'string', 'max:60'],
            'period' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Application::query()->with('user:id,name,service_number,assigned_cgis_unit_code');

        if ($category = $filters['category'] ?? null) {
            if ($category === 'cgis') {
                $query->whereHas('user', fn ($q) => $q->whereNotNull('assigned_cgis_unit_code')->where('assigned_cgis_unit_code', '!=', ''));
            } else {
                $query->where('category', $category);
            }
        }

        if ($formation = $filters['formation'] ?? null) {
            $query->where('scope_code', $formation);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($stage = $filters['stage'] ?? null) {
            $query->where('workflow_stage', $stage);
        }

        if ($period = $filters['period'] ?? null) {
            $query->where('return_data->report_period', $period);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('return_data->reporting_officer', 'like', "%{$search}%")
                    ->orWhere('return_data->command_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($inner) => $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('service_number', 'like', "%{$search}%"));
            });
        }

        $applications = $query->latest()->paginate(20)->withQueryString();

        return view('admin.headquarters.returns', [
            'applications' => $applications,
            'directorates' => self::DIRECTORATES,
            'stageLabels' => self::STAGE_LABELS,
            'filters' => $filters,
        ]);
    }

    public function show(Application $application): View
    {
        abort_unless(request()->user()?->can('view', $application), 403);

        $application->load(['user:id,name,service_number,email,assigned_cgis_unit_code', 'reviewComments.user']);

        $actorIds = collect($application->workflow_path ?? [])->pluck('by')->filter()->unique();
        $actors = User::query()->whereIn('id', $actorIds)->pluck('name', 'id');

        $timeline = collect($application->workflow_path ?? [])->map(function (array $entry) use ($actors) {
            return [
                'stage' => self::STAGE_LABELS[$entry['stage'] ?? ''] ?? ucwords(str_replace('_', ' ', (string) ($entry['stage'] ?? 'unknown'))),
                'action' => $entry['action'] ?? 'submitted',
                'by' => $actors[$entry['by'] ?? null] ?? 'System',
                'at' => $entry['at'] ?? null,
            ];
        });

        return view('admin.headquarters.return-show', [
            'application' => $application,
            'timeline' => $timeline,
            'stageLabels' => self::STAGE_LABELS,
        ]);
    }

    public function archive(Request $request): View
    {
        $filters = $request->validate([
            'formation' => ['nullable', 'string', 'max:60'],
            'period' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Application::query()
            ->with('user:id,name,service_number,assigned_cgis_unit_code')
            ->where('status', 'approved');

        if ($formation = $filters['formation'] ?? null) {
            $query->where('scope_code', $formation);
        }

        if ($period = $filters['period'] ?? null) {
            $query->where('return_data->report_period', $period);
        }

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('return_data->reporting_officer', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($inner) => $inner
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('service_number', 'like', "%{$search}%"));
            });
        }

        $archived = $query->latest('updated_at')->paginate(20)->withQueryString();

        return view('admin.headquarters.archive', [
            'archived' => $archived,
            'directorates' => self::DIRECTORATES,
            'filters' => $filters,
        ]);
    }

    public function analytics(Request $request, HqAnalyticsService $analyticsService): View
    {
        $payload = $analyticsService->build($request->only(['range', 'period', 'category', 'zone', 'status']));

        return view('admin.headquarters.analytics', $payload);
    }

    public function exportAnalyticsCsv(Request $request, HqAnalyticsService $analyticsService): StreamedResponse
    {
        $payload = $analyticsService->build($request->only(['range', 'period', 'category', 'zone', 'status']));
        $filename = 'nis-redas-hq-analytics-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['NIS-REDAS HQ Analytics Export', 'Generated At', now()->toDateTimeString()]);
            fputcsv($out, []);

            fputcsv($out, ['EXECUTIVE KPI SUMMARY']);
            fputcsv($out, ['Metric', 'Value']);
            foreach ($payload['kpis'] as $metric => $value) {
                fputcsv($out, [ucwords(str_replace('_', ' ', (string) $metric)), $value ?? 'N/A']);
            }
            fputcsv($out, []);

            fputcsv($out, ['ZONAL COMMAND MATRIX']);
            fputcsv($out, ['Zone Code', 'Zone Name', 'Total Returns', 'Approved', 'Pending', 'Returned', 'Reporting Units', 'Approval Rate (%)', 'Consolidated Status']);
            foreach ($payload['zonalMatrix'] as $row) {
                fputcsv($out, [
                    $row['code'],
                    $row['name'],
                    $row['total'],
                    $row['approved'],
                    $row['pending'],
                    $row['returned'],
                    $row['reporting_units'],
                    $row['approval_rate'],
                    $row['consolidated_status'] ?? 'None',
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['FORMATION LEADERBOARD']);
            fputcsv($out, ['Scope Code', 'Formation Name', 'Category', 'Zone', 'Total', 'Approved', 'Pending', 'Returned', 'Approval Rate (%)', 'Last Submitted']);
            foreach ($payload['formationLeaderboard'] as $row) {
                fputcsv($out, [
                    $row['scope_code'],
                    $row['name'],
                    $row['category'],
                    $row['zone'],
                    $row['total'],
                    $row['approved'],
                    $row['pending'],
                    $row['returned'],
                    $row['approval_rate'],
                    $row['last_submitted'],
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['WORKFLOW STAGE TURNAROUND']);
            fputcsv($out, ['Stage', 'Samples', 'Avg Hours', 'Min Hours', 'Max Hours', 'Health']);
            foreach ($payload['turnaround'] as $row) {
                fputcsv($out, [
                    $row['stage'],
                    $row['samples'],
                    $row['avg_hours'],
                    $row['min_hours'],
                    $row['max_hours'],
                    $row['health'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reports(): View
    {
        return view('admin.headquarters.reports', [
            'templates' => ExcelReportService::TEMPLATES,
        ]);
    }

    public function generateReport(Request $request, ExcelReportService $excel): BinaryFileResponse|RedirectResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:quarterly,biannual,annual'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'part' => ['nullable', 'integer', 'min:0', 'max:4'],
        ]);

        $reportType = $validated['report_type'];
        $year = (int) $validated['year'];
        $part = isset($validated['part']) ? (int) $validated['part'] : null;

        try {
            if ($reportType !== 'annual'
                && ($part === null || ! isset(ExcelReportService::TEMPLATES[$reportType]['parts'][$part]))) {
                return back()
                    ->withInput()
                    ->with('error', 'Please choose a valid period part for the selected report template.');
            }

            [$title, $label, $from, $to] = ExcelReportService::periodFor($reportType, $year, $part);

            $applications = Application::query()
                ->with('user:id,name,service_number,assigned_cgis_unit_code')
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('scope_code')
                ->orderBy('created_at')
                ->get();

            if ($applications->isEmpty()) {
                return back()
                    ->withInput()
                    ->with('error', "No returns were submitted in {$label} {$year}.");
            }

            $path = $excel->generate($reportType, $year, $part, $applications);

            \App\Services\AuditLogger::log(
                user: $request->user(),
                action: 'hq_report_generated',
                status: 'success',
                entityType: 'report',
                entityId: null,
                details: [
                    'report_type' => $reportType,
                    'year' => $year,
                    'part' => $part,
                    'period' => "{$from->toDateString()} to {$to->toDateString()}",
                    'returns_included' => $applications->count(),
                ],
                ip: $request->ip()
            );

            $filename = str(sprintf('redas-%s-report-%d', $reportType, $year))
                ->when($part, fn ($name) => $name->append('-part-' . $part))
                ->append('.xlsx')
                ->toString();

            return response()->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend();
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'The report could not be generated. Please try again.');
        }
    }
}
