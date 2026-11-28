<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\Application;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Files\SecureFileStorage;
use App\Services\ReportScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGenerationController extends Controller
{
    public const ALL_SECTIONS = [
        'hrm' => 'HRM',
        'border' => 'Border Management',
        'finance' => 'Finance & Accounts',
        'ic' => 'Investigation & Compliance',
        'prs' => 'PRS',
        'migration' => 'Migration',
        'ict' => 'ICT',
        'works' => 'Works & Logistics',
        'visa' => 'Visa & Residency',
        'passport' => 'Passport & Travel',
    ];

    public const DIRECTORATE_SLUGS_TO_SECTION = [
        'hrm' => 'hrm',
        'finance' => 'finance',
        'border' => 'border',
        'migration' => 'migration',
        'passport' => 'passport',
        'visa' => 'visa',
        'prs' => 'prs',
        'investigation' => 'ic',
        'ict' => 'ict',
        'works-logistics' => 'works',
    ];

    public const CGIS_UNITS = ReportScopeService::CGIS_UNITS;

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $ctx = $this->builderContext($user);

        $activeFilters = [
            'date_from' => (string) ($request->query('date_from') ?: now()->startOfYear()->format('Y-m-d')),
            'date_to' => (string) ($request->query('date_to') ?: now()->format('Y-m-d')),
            'status_filter' => (string) ($request->query('status_filter') ?: 'all'),
            'category_filter' => (string) ($request->query('category_filter') ?: ''),
            'zone_code' => (string) ($request->query('zone_code') ?: ''),
            'command_code' => (string) ($request->query('command_code') ?: ''),
            'directorate_code' => (string) ($request->query('directorate_code') ?: ''),
            'section_code' => (string) ($request->query('section_code') ?: ''),
            'cgis_unit_code' => (string) ($request->query('cgis_unit_code') ?: ''),
        ];

        $baseQuery = ReportScopeService::applyFilters(
            Application::query()->with('user:id,name,service_number,user_category'),
            $user,
            $activeFilters
        );

        $kpiCounts = [
            'total' => (clone $baseQuery)->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'returned' => (clone $baseQuery)->whereIn('status', ['returned', 'rejected', 'queried'])->count(),
        ];

        $previewReturns = $baseQuery
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $recentReports = GeneratedReport::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        return view('user.states.reports', array_merge(
            [
                'recentReports' => $recentReports,
                'activeFilters' => $activeFilters,
                'kpiCounts' => $kpiCounts,
                'previewReturns' => $previewReturns,
            ],
            $ctx
        ));
    }

    public function generate(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $ctx = $this->builderContext($user);

        $allowedReportTypes = $ctx['scopeLevel'] === 'national'
            ? 'submission_summary,monthly_return,quarterly_return,annual_return,compliance_report,consolidated_summary,full_export'
            : 'submission_summary,monthly_return,quarterly_return,annual_return,compliance_report,full_export';

        $validated = $request->validate([
            'report_type' => ['required', 'in:'.$allowedReportTypes],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'max:50'],
            'format' => ['required', 'in:pdf,excel,csv'],
            'category_filter' => ['nullable', 'in:,state,zone,directorate,cgis'],
            'zone_code' => ['nullable', 'string', 'max:30'],
            'command_code' => ['nullable', 'string', 'max:50'],
            'directorate_code' => ['nullable', 'string', 'max:50'],
            'section_code' => ['nullable', 'string', 'max:80'],
            'cgis_unit_code' => ['nullable', 'string', 'max:50'],
            'status_filter' => ['nullable', 'in:all,approved,pending,returned'],
        ]);

        $allowedSectionKeys = array_keys($ctx['availableSections']);
        if ($ctx['sectionsLocked']) {
            $sections = $allowedSectionKeys;
        } else {
            $requestedSections = array_values(array_intersect(
                array_values($validated['sections'] ?? []),
                $allowedSectionKeys
            ));
            $sections = ($requestedSections === [] || count($requestedSections) === count($allowedSectionKeys))
                ? []
                : $requestedSections;
        }

        $filters = [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'sections' => $sections,
            'scope_label' => $ctx['scopeLabel'],
            'scope_level' => $ctx['scopeLevel'],
            'status_filter' => $validated['status_filter'] ?? 'all',
        ];

        if ($ctx['scopeLevel'] === 'national') {
            if (! empty($validated['category_filter'])) {
                $filters['category_filter'] = strtolower(trim($validated['category_filter']));
            }
            if (! empty($validated['zone_code'])) {
                $filters['zone_code'] = strtoupper(trim($validated['zone_code']));
            }
            if (! empty($validated['command_code'])) {
                $filters['command_code'] = strtoupper(trim($validated['command_code']));
            }
            if (! empty($validated['directorate_code'])) {
                $filters['directorate_code'] = ReportScopeService::normalizeDirectorateSlug($validated['directorate_code']);
            }
            if (! empty($validated['cgis_unit_code'])) {
                $filters['cgis_unit_code'] = strtolower(trim($validated['cgis_unit_code']));
            }
        } elseif ($ctx['scopeLevel'] === 'zone' && ! empty($validated['command_code'])) {
            $requestedCmd = strtoupper(trim($validated['command_code']));
            if (array_key_exists($requestedCmd, $ctx['zonalCommandOptions'])) {
                $filters['command_code'] = $requestedCmd;
            }
        } elseif ($ctx['scopeLevel'] === 'directorate' && ! empty($validated['section_code'])) {
            $filters['section_code'] = trim($validated['section_code']);
        }

        $report = GeneratedReport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report_type' => $validated['report_type'],
            'format' => $validated['format'],
            'filters' => $filters,
            'status' => 'queued',
        ]);

        GenerateReportJob::dispatch($report->id);

        $report->refresh();

        if ($report->status === 'completed') {
            return redirect()->route('user.reports.download', $report);
        }

        if ($report->status === 'failed') {
            return redirect()
                ->route('user.reports')
                ->with('error', 'Report generation failed: '.($report->error ?: 'Unknown error.'));
        }

        return redirect()
            ->route('user.reports')
            ->with('status', 'Your report has been queued for generation. You will be notified once it is ready for download.');
    }

    public function download(GeneratedReport $generatedReport): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($generatedReport->user_id === $user->id, 403, 'You are not authorized to download this report.');
        abort_unless($generatedReport->status === 'completed' && is_string($generatedReport->stored_path), 404, 'Report file is not ready.');

        $safePath = SecureFileStorage::safePath($generatedReport->stored_path);
        abort_if($safePath === null, 404);

        AuditLogger::log(
            user: $user,
            action: 'report.downloaded',
            status: 'success',
            entityType: 'generated_report',
            entityId: $generatedReport->id,
            details: [
                'uuid' => $generatedReport->uuid,
                'report_type' => $generatedReport->report_type,
                'format' => $generatedReport->format,
            ]
        );

        $ext = match ($generatedReport->format) {
            'excel' => 'xlsx',
            'csv' => 'csv',
            default => 'pdf',
        };
        $filename = sprintf('nis-redas-%s-%s.%s', $generatedReport->report_type, $generatedReport->created_at->format('Ymd-His'), $ext);

        return SecureFileStorage::streamResponse($safePath, $filename, forceDownload: true);
    }

    /**
     * Resolve institutional scope & filter options for the authenticated user.
     *
     * @return array<string, mixed>
     */
    private function builderContext(User $user): array
    {
        $zonesConfig = (array) config('nis_hierarchy.zones', []);
        $scopeLevel = ReportScopeService::scopeLevel($user);

        if ($scopeLevel === 'national') {
            $zoneOptions = [];
            $groupedCommands = [];
            foreach ($zonesConfig as $zoneCode => $zoneData) {
                $zoneOptions[$zoneCode] = "{$zoneCode} - {$zoneData['name']}";
                $cmdList = [$zoneCode => "{$zoneCode} - {$zoneData['name']} (Zone HQ)"];
                foreach (($zoneData['commands'] ?? []) as $cmdCode => $cmdName) {
                    $cmdList[$cmdCode] = "{$cmdCode} - {$cmdName}";
                }
                $groupedCommands["{$zoneCode} - {$zoneData['name']}"] = $cmdList;
            }

            return [
                'scopeLevel' => 'national',
                'isPrsSuper' => true,
                'builderTitle' => 'Report Builder',
                'builderSubtitle' => 'National Scope — All Zones, State Commands, Formations, Directorates, and CGIS Units.',
                'scopeLabel' => 'National Scope',
                'availableSections' => self::ALL_SECTIONS,
                'sectionsLocked' => false,
                'zoneOptions' => $zoneOptions,
                'groupedCommands' => $groupedCommands,
                'zonalCommandOptions' => [],
                'directorateOptions' => ReportScopeService::DIRECTORATES,
                'directorateSectionOptions' => [],
                'cgisUnitOptions' => ReportScopeService::CGIS_UNITS,
            ];
        }

        if ($scopeLevel === 'zone') {
            $zoneCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_zonal_command_code)));
            $zoneInfo = $zonesConfig[$zoneCode] ?? null;
            $zoneName = $zoneInfo['name'] ?? ($zoneCode !== '' ? $zoneCode : 'Assigned Zone');
            $zonalCommands = [];
            if ($zoneCode !== '') {
                $zonalCommands[$zoneCode] = "{$zoneCode} - {$zoneName} (Zone HQ)";
            }
            foreach (($zoneInfo['commands'] ?? []) as $cmdCode => $cmdName) {
                $zonalCommands[strtoupper($cmdCode)] = strtoupper($cmdCode)." - {$cmdName}";
            }

            return [
                'scopeLevel' => 'zone',
                'isPrsSuper' => false,
                'builderTitle' => 'Report Builder',
                'builderSubtitle' => "Zonal Scope — {$zoneCode} ({$zoneName}) and subordinate State Commands.",
                'scopeLabel' => "{$zoneCode} - {$zoneName}",
                'availableSections' => self::ALL_SECTIONS,
                'sectionsLocked' => false,
                'zoneOptions' => [],
                'groupedCommands' => [],
                'zonalCommandOptions' => $zonalCommands,
                'directorateOptions' => [],
                'directorateSectionOptions' => [],
                'cgisUnitOptions' => [],
            ];
        }

        if ($scopeLevel === 'directorate') {
            $dirSlug = strtolower(trim((string) ($user->directorateSlug() ?: $user->assigned_directorate_code)));
            $sectionKey = self::DIRECTORATE_SLUGS_TO_SECTION[$dirSlug] ?? $dirSlug;
            $dirLabel = ReportScopeService::DIRECTORATES[$dirSlug] ?? (self::ALL_SECTIONS[$sectionKey] ?? strtoupper($dirSlug ?: 'Directorate'));
            $dirSections = ReportScopeService::sectionsForDirectorate($dirSlug);

            return [
                'scopeLevel' => 'directorate',
                'isPrsSuper' => false,
                'builderTitle' => 'Report Builder',
                'builderSubtitle' => "Directorate Scope — {$dirLabel} and subordinate sections.",
                'scopeLabel' => $dirLabel,
                'availableSections' => [$sectionKey => $dirLabel],
                'sectionsLocked' => true,
                'zoneOptions' => [],
                'groupedCommands' => [],
                'zonalCommandOptions' => [],
                'directorateOptions' => [],
                'directorateSectionOptions' => $dirSections,
                'cgisUnitOptions' => [],
            ];
        }

        if ($scopeLevel === 'cgis') {
            $unitSlug = strtolower(trim((string) ($user->cgisUnitSlug() ?: $user->assigned_cgis_unit_code)));
            $unitLabel = ReportScopeService::CGIS_UNITS[$unitSlug] ?? strtoupper($unitSlug ?: 'CGIS Unit');

            return [
                'scopeLevel' => 'cgis',
                'isPrsSuper' => false,
                'builderTitle' => 'Report Builder',
                'builderSubtitle' => "CGIS Office Scope — {$unitLabel}.",
                'scopeLabel' => $unitLabel,
                'availableSections' => [$unitSlug ?: 'cgis' => $unitLabel],
                'sectionsLocked' => true,
                'zoneOptions' => [],
                'groupedCommands' => [],
                'zonalCommandOptions' => [],
                'directorateOptions' => [],
                'directorateSectionOptions' => [],
                'cgisUnitOptions' => [],
            ];
        }

        // State / Formation Officer or Desk Admin
        $cmdCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_state_code)));
        $cmdLabel = $this->resolveCommandLabel($cmdCode, $zonesConfig);

        return [
            'scopeLevel' => 'state',
            'isPrsSuper' => false,
            'builderTitle' => 'Report Builder',
            'builderSubtitle' => "State Command Scope — {$cmdLabel}.",
            'scopeLabel' => $cmdLabel,
            'availableSections' => self::ALL_SECTIONS,
            'sectionsLocked' => false,
            'zoneOptions' => [],
            'groupedCommands' => [],
            'zonalCommandOptions' => [],
            'directorateOptions' => [],
            'directorateSectionOptions' => [],
            'cgisUnitOptions' => [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $zonesConfig
     */
    private function resolveCommandLabel(string $code, array $zonesConfig): string
    {
        if ($code === '') {
            return 'Assigned State Command';
        }

        foreach ($zonesConfig as $zoneCode => $zoneInfo) {
            $commands = (array) ($zoneInfo['commands'] ?? []);
            if (isset($commands[$code])) {
                return "{$code} - {$commands[$code]} ({$zoneCode})";
            }
        }

        return $code;
    }
}
