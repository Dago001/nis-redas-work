<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Files\SecureFileStorage;
use App\Services\ReportAccess;
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

    public const CGIS_UNITS = [
        'actu' => 'ACTU (Anti-Corruption & Transparency Unit)',
        'epms' => 'EPMS (Electronic Performance Management)',
        'hostmanship' => 'Hostmanship Unit',
        'pro-media' => 'PRO / Public Relations & Media',
        'protocol' => 'Protocol Unit',
        'provost' => 'Provost Unit',
        'servicom' => 'SERVICOM Unit',
    ];

    public function index(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $recentReports = GeneratedReport::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        return view('user.states.reports', array_merge(
            ['recentReports' => $recentReports],
            $this->builderContext($user)
        ));
    }

    public function generate(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $ctx = $this->builderContext($user);

        $allowedReportTypes = $ctx['isPrsSuper']
            ? 'submission_summary,monthly_return,quarterly_return,annual_return,compliance_report,consolidated_summary,full_export'
            : 'submission_summary,monthly_return,quarterly_return,annual_return,compliance_report,full_export';

        $validated = $request->validate([
            'report_type' => ['required', 'in:'.$allowedReportTypes],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'max:50'],
            'format' => ['required', 'in:pdf,excel,csv'],
            'zone_code' => ['nullable', 'string', 'max:30'],
            'command_code' => ['nullable', 'string', 'max:50'],
            'directorate_code' => ['nullable', 'string', 'max:50'],
            'cgis_unit_code' => ['nullable', 'string', 'max:50'],
            'status_filter' => ['nullable', 'in:all,approved,pending,returned'],
        ]);

        // Enforce server-side scope & section lock
        $allowedSectionKeys = array_keys($ctx['availableSections']);
        if ($ctx['sectionsLocked']) {
            $sections = $allowedSectionKeys;
        } else {
            $requestedSections = array_values(array_intersect(
                array_values($validated['sections'] ?? []),
                $allowedSectionKeys
            ));
            // Empty or all sections selected means no artificial section exclusion
            $sections = ($requestedSections === [] || count($requestedSections) === count($allowedSectionKeys))
                ? []
                : $requestedSections;
        }

        $filters = [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'sections' => $sections,
            'scope_label' => $ctx['scopeLabel'],
            'is_prs_super' => $ctx['isPrsSuper'],
            'status_filter' => $validated['status_filter'] ?? 'all',
        ];

        if ($ctx['isPrsSuper']) {
            if (! empty($validated['zone_code'])) {
                $filters['zone_code'] = strtoupper(trim($validated['zone_code']));
            }
            if (! empty($validated['command_code'])) {
                $filters['command_code'] = strtoupper(trim($validated['command_code']));
            }
            if (! empty($validated['directorate_code'])) {
                $filters['directorate_code'] = strtolower(trim($validated['directorate_code']));
            }
            if (! empty($validated['cgis_unit_code'])) {
                $filters['cgis_unit_code'] = strtolower(trim($validated['cgis_unit_code']));
            }
        } elseif ($user->user_category === 'zonal_commander' && ! empty($validated['command_code'])) {
            $requestedCmd = strtoupper(trim($validated['command_code']));
            if (array_key_exists($requestedCmd, $ctx['zonalCommandOptions'])) {
                $filters['command_code'] = $requestedCmd;
            }
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
     * Resolve command/unit-scoped vs PRS Super & Comprehensive builder context.
     *
     * @return array<string, mixed>
     */
    private function builderContext(User $user): array
    {
        $zonesConfig = (array) config('nis_hierarchy.zones', []);
        $isPrsSuper = ReportAccess::isPrsViewer($user) || $user->directorateSlug() === 'prs';

        if ($isPrsSuper) {
            $zoneOptions = [];
            $groupedCommands = [];
            foreach ($zonesConfig as $zoneCode => $zoneData) {
                $zoneOptions[$zoneCode] = "{$zoneCode} - {$zoneData['name']}";
                $cmdList = [];
                foreach (($zoneData['commands'] ?? []) as $cmdCode => $cmdName) {
                    $cmdList[$cmdCode] = "{$cmdCode} - {$cmdName}";
                }
                $groupedCommands["{$zoneCode} - {$zoneData['name']}"] = $cmdList;
            }

            return [
                'isPrsSuper' => true,
                'builderTitle' => 'PRS Super & Comprehensive Report Builder',
                'builderSubtitle' => 'National multi-command intelligence & reporting across all 8 NIS Zones, 52 Commands, 10 Directorates, and 7 CGIS Units.',
                'scopeLabel' => 'National Scope (PRS Comprehensive)',
                'scopeBadge' => 'PRS SUPER ACCESS',
                'availableSections' => self::ALL_SECTIONS,
                'sectionsLocked' => false,
                'zoneOptions' => $zoneOptions,
                'groupedCommands' => $groupedCommands,
                'zonalCommandOptions' => [],
                'directorateOptions' => self::ALL_SECTIONS,
                'cgisUnitOptions' => self::CGIS_UNITS,
            ];
        }

        // Directorate officer / non-PRS directorate admin -> strictly locked to their own directorate
        if (in_array($user->user_category, ['directorate_user', 'directorate_admin'], true)) {
            $dirSlug = $user->directorateSlug() ?? strtolower((string) $user->assigned_directorate_code);
            $sectionKey = self::DIRECTORATE_SLUGS_TO_SECTION[$dirSlug] ?? $dirSlug;
            $dirLabel = self::ALL_SECTIONS[$sectionKey] ?? strtoupper($dirSlug ?: 'Directorate');

            return [
                'isPrsSuper' => false,
                'builderTitle' => "{$dirLabel} Directorate Report Builder",
                'builderSubtitle' => "Generate PDF, Excel, or CSV reports strictly scoped to the {$dirLabel} Directorate.",
                'scopeLabel' => "{$dirLabel} Directorate",
                'scopeBadge' => 'DIRECTORATE SCOPED',
                'availableSections' => [$sectionKey => $dirLabel],
                'sectionsLocked' => true,
                'zoneOptions' => [],
                'groupedCommands' => [],
                'zonalCommandOptions' => [],
                'directorateOptions' => [],
                'cgisUnitOptions' => [],
            ];
        }

        // CGIS Unit officer / CGIS desk admin -> strictly locked to their own CGIS unit
        if (in_array($user->user_category, ['cgis_unit_user', 'cgis_desk_admin'], true)) {
            $unitSlug = $user->cgisUnitSlug() ?? strtolower((string) $user->assigned_cgis_unit_code);
            $unitLabel = self::CGIS_UNITS[$unitSlug] ?? strtoupper($unitSlug ?: 'CGIS Unit');

            return [
                'isPrsSuper' => false,
                'builderTitle' => "{$unitLabel} Report Builder",
                'builderSubtitle' => "Generate PDF, Excel, or CSV reports strictly scoped to {$unitLabel}.",
                'scopeLabel' => $unitLabel,
                'scopeBadge' => 'CGIS UNIT SCOPED',
                'availableSections' => [$unitSlug ?: 'cgis' => $unitLabel],
                'sectionsLocked' => true,
                'zoneOptions' => [],
                'groupedCommands' => [],
                'zonalCommandOptions' => [],
                'directorateOptions' => [],
                'cgisUnitOptions' => [],
            ];
        }

        // Zonal Commander -> strictly locked to their own Zone & its child commands
        if ($user->user_category === 'zonal_commander') {
            $zoneCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_zonal_command_code)));
            $zoneInfo = $zonesConfig[$zoneCode] ?? null;
            $zoneName = $zoneInfo['name'] ?? ($zoneCode !== '' ? $zoneCode : 'Assigned Zone');
            $zonalCommands = [];
            foreach (($zoneInfo['commands'] ?? []) as $cmdCode => $cmdName) {
                $zonalCommands[$cmdCode] = "{$cmdCode} - {$cmdName}";
            }

            return [
                'isPrsSuper' => false,
                'builderTitle' => "{$zoneName} Report Builder",
                'builderSubtitle' => "Generate reports strictly for {$zoneName} and the ".count($zonalCommands).' commands under your zonal oversight.',
                'scopeLabel' => "{$zoneCode} - {$zoneName}",
                'scopeBadge' => 'ZONAL SCOPED',
                'availableSections' => self::ALL_SECTIONS,
                'sectionsLocked' => false,
                'zoneOptions' => [],
                'groupedCommands' => [],
                'zonalCommandOptions' => $zonalCommands,
                'directorateOptions' => [],
                'cgisUnitOptions' => [],
            ];
        }

        // Default: State / Formation Officer or Formation Desk Admin -> strictly locked to their Command
        $cmdCode = strtoupper(trim((string) ($user->primary_location_code ?: $user->assigned_state_code)));
        $cmdLabel = $this->resolveCommandLabel($cmdCode, $zonesConfig);

        return [
            'isPrsSuper' => false,
            'builderTitle' => "{$cmdLabel} Report Builder",
            'builderSubtitle' => "Generate PDF, Excel, or CSV reports strictly scoped to {$cmdLabel}.",
            'scopeLabel' => $cmdLabel,
            'scopeBadge' => 'COMMAND SCOPED',
            'availableSections' => self::ALL_SECTIONS,
            'sectionsLocked' => false,
            'zoneOptions' => [],
            'groupedCommands' => [],
            'zonalCommandOptions' => [],
            'directorateOptions' => [],
            'cgisUnitOptions' => [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $zonesConfig
     */
    private function resolveCommandLabel(string $code, array $zonesConfig): string
    {
        if ($code === '') {
            return 'Assigned Command';
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
