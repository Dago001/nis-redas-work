<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ConsolidatedReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HqAnalyticsService
{
    public const DIRECTORATES = [
        'hrm' => ['short' => 'HRM', 'name' => 'Human Resources Management (HRM)'],
        'prs' => ['short' => 'PRS', 'name' => 'Planning, Research & Statistics (PRS)'],
        'finance' => ['short' => 'F&A', 'name' => 'Finance & Accounts (F/A)'],
        'investigation' => ['short' => 'I&C', 'name' => 'Investigation & Compliance (I/C)'],
        'passport' => ['short' => 'P/OTD', 'name' => 'Passport & Travel Docs (P/OTD)'],
        'visa' => ['short' => 'Visa', 'name' => 'Visa & Residency (V/R)'],
        'migration' => ['short' => 'Migration', 'name' => 'Migration Directorate'],
        'border' => ['short' => 'Border Mgt', 'name' => 'Border Management'],
        'ict' => ['short' => 'ICT', 'name' => 'ICT / Cybersecurity'],
        'works-logistics' => ['short' => 'Works', 'name' => 'Works & Logistics'],
    ];

    public const ZONES = [
        'ZONE-A' => 'Zone A (Lagos / Ogun)',
        'ZONE-B' => 'Zone B (Kaduna / North-West)',
        'ZONE-C' => 'Zone C (Bauchi / North-East)',
        'ZONE-D' => 'Zone D (Minna / North-Central)',
        'ZONE-E' => 'Zone E (Owerri / South-East)',
        'ZONE-F' => 'Zone F (Ibadan / South-West)',
        'ZONE-G' => 'Zone G (Benin / South-South)',
        'ZONE-H' => 'Zone H (Makurdi / Middle Belt)',
    ];

    public const STAGE_LABELS = [
        SubmissionWorkflow::STAGE_SUBMITTED => 'Submitted',
        SubmissionWorkflow::STAGE_DESK_REVIEW => 'Desk Admin Review',
        SubmissionWorkflow::STAGE_ZONAL_REVIEW => 'Zonal Review',
        SubmissionWorkflow::STAGE_DIRECTORATE_REVIEW => 'Directorate Review',
        SubmissionWorkflow::STAGE_HQ_REVIEW => 'HQ Admin Review',
        SubmissionWorkflow::STAGE_ADMIN_REVIEW => 'Final Approval',
        SubmissionWorkflow::STAGE_APPROVED => 'Approved',
    ];

    /**
     * Map return_data key prefixes to canonical directorate slugs for state-return section coverage.
     *
     * @var array<string, list<string>>
     */
    private const STATE_SECTION_PREFIXES = [
        'hrm' => ['hrm_', 'records_', 'registry_', 'cadre_', 'nominal_', 'staff_', 'training_'],
        'prs' => ['prs_', 'research_', 'statistics_', 'planning_', 'servicom_'],
        'finance' => ['finance_', 'local_revenue_', 'revenue_', 'budget_', 'expenditure_', 'remittance_'],
        'investigation' => ['inv_', 'breach_', 'dfu_', 'dofit_', 'citizenship_', 'suspect_', 'deportation_', 'repatriation_'],
        'passport' => ['passport_', 'ecowas_', 'etc_', 'potd_', 'booklet_'],
        'visa' => ['visa_', 'cerpac_', 'residency_', 'quota_', 'str_', 'twp_', 'evp_'],
        'migration' => ['migration_', 'smuggling_', 'trafficking_', 'regular_', 'irregular_', 'asylum_'],
        'border' => ['border_', 'patrol_', 'arrival_', 'departure_', 'seaport_', 'airport_', 'land_border_'],
        'ict' => ['ict_', 'cyber_', 'network_', 'hardware_', 'software_', 'system_'],
        'works-logistics' => ['works_', 'logistics_', 'arms_', 'armoury_', 'vehicle_', 'store_', 'project_', 'building_'],
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $rawRange = (string) ($filters['range'] ?? 'all');
        $rawPeriod = is_string($filters['period'] ?? null) ? $filters['period'] : '';
        $rawCategory = (string) ($filters['category'] ?? '');
        $rawZone = strtoupper((string) ($filters['zone'] ?? ''));
        $rawStatus = (string) ($filters['status'] ?? '');

        $cleanFilters = [
            'range' => in_array($rawRange, ['all', 'current_month', '3m', '6m', '12m'], true) ? $rawRange : 'all',
            'period' => preg_match('/^\d{4}-\d{2}$/', $rawPeriod) ? $rawPeriod : '',
            'category' => in_array($rawCategory, ['state', 'directorate', 'cgis_unit'], true) ? $rawCategory : '',
            'zone' => array_key_exists($rawZone, self::ZONES) ? $rawZone : '',
            'status' => in_array($rawStatus, ['pending', 'approved', 'returned'], true) ? $rawStatus : '',
        ];

        $apps = $this->filteredApplicationsQuery($cleanFilters)
            ->with('user:id,name,service_number,user_category,assigned_zonal_command_code,assigned_state_code,assigned_directorate_code,assigned_cgis_unit_code')
            ->latest('created_at')
            ->get();

        $total = $apps->count();
        $approved = $apps->filter(fn (Application $a) => strtolower((string) $a->status) === 'approved')->count();
        $returned = $apps->filter(fn (Application $a) => in_array(strtolower((string) $a->status), ['returned', 'rejected', 'queried'], true))->count();
        $pending = max(0, $total - $approved - $returned);
        $awaitingHq = $apps->filter(fn (Application $a) => $a->workflow_stage === SubmissionWorkflow::STAGE_HQ_REVIEW
            && ! in_array(strtolower((string) $a->status), ['approved', 'returned', 'rejected'], true))->count();

        $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;
        $returnRate = $total > 0 ? round(($returned / $total) * 100, 1) : 0.0;

        $activeFormations = $apps
            ->map(fn (Application $a) => strtoupper((string) ($a->scope_code ?? '')))
            ->filter(fn (string $code) => $code !== '')
            ->unique()
            ->count();

        // Month-over-month velocity (unfiltered by range/period so HQ always sees current velocity)
        $now = Carbon::now();
        $prevMonth = $now->copy()->subMonthNoOverflow();
        $currentMonthReturns = Application::query()
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();
        $previousMonthReturns = Application::query()
            ->whereYear('created_at', $prevMonth->year)
            ->whereMonth('created_at', $prevMonth->month)
            ->count();
        $momDeltaPct = $previousMonthReturns > 0
            ? round((($currentMonthReturns - $previousMonthReturns) / $previousMonthReturns) * 100, 1)
            : ($currentMonthReturns > 0 ? 100.0 : 0.0);

        // Consolidated reports query (respects period & zone if set)
        $consolidatedQuery = ConsolidatedReport::query()->with(['creator:id,name', 'approver:id,name']);
        if ($cleanFilters['period'] !== '') {
            $consolidatedQuery->where('report_period', $cleanFilters['period']);
        }
        if ($cleanFilters['zone'] !== '') {
            $consolidatedQuery->where('scope_code', $cleanFilters['zone']);
        }
        $consolidatedReports = $consolidatedQuery->latest('updated_at')->get();

        $consolidatedCounts = [
            'total' => $consolidatedReports->count(),
            'draft' => $consolidatedReports->where('status', ConsolidatedReport::STATUS_DRAFT)->count(),
            'submitted' => $consolidatedReports->where('status', ConsolidatedReport::STATUS_SUBMITTED)->count(),
            'returned' => $consolidatedReports->where('status', ConsolidatedReport::STATUS_RETURNED)->count(),
            'approved' => $consolidatedReports->where('status', ConsolidatedReport::STATUS_APPROVED)->count(),
            'stale' => $consolidatedReports->where('is_stale', true)->count(),
        ];

        // 12-Month Trend (respects category/zone/status filters)
        $trendFilters = array_merge($cleanFilters, ['range' => 'all', 'period' => '']);
        $trendApps = $this->filteredApplicationsQuery($trendFilters)
            ->where('created_at', '>=', $now->copy()->subMonths(11)->startOfMonth())
            ->get(['id', 'status', 'created_at']);

        $trendLabels = [];
        $trendSubmitted = [];
        $trendApproved = [];
        $trendReturned = [];

        for ($i = 11; $i >= 0; $i--) {
            $m = $now->copy()->subMonths($i);
            $ym = $m->format('Y-m');
            $trendLabels[] = $m->format('M Y');

            $monthSlice = $trendApps->filter(fn (Application $a) => optional($a->created_at)->format('Y-m') === $ym);
            $trendSubmitted[] = $monthSlice->count();
            $trendApproved[] = $monthSlice->filter(fn (Application $a) => strtolower((string) $a->status) === 'approved')->count();
            $trendReturned[] = $monthSlice->filter(fn (Application $a) => in_array(strtolower((string) $a->status), ['returned', 'rejected', 'queried'], true))->count();
        }

        // Status Distribution
        $statusChart = [
            'labels' => ['Approved', 'Pending / In Flight', 'Returned / Queried'],
            'data' => [$approved, $pending, $returned],
        ];

        // Workflow Stage Pipeline
        $orderedStages = [
            SubmissionWorkflow::STAGE_SUBMITTED,
            SubmissionWorkflow::STAGE_DESK_REVIEW,
            SubmissionWorkflow::STAGE_ZONAL_REVIEW,
            SubmissionWorkflow::STAGE_DIRECTORATE_REVIEW,
            SubmissionWorkflow::STAGE_HQ_REVIEW,
            SubmissionWorkflow::STAGE_APPROVED,
        ];
        $stageCounts = [];
        foreach ($orderedStages as $stage) {
            $stageCounts[$stage] = $apps->where('workflow_stage', $stage)->count();
        }
        $stageChart = [
            'labels' => collect($orderedStages)->map(fn (string $s) => self::STAGE_LABELS[$s] ?? ucwords(str_replace('_', ' ', $s)))->all(),
            'data' => array_values($stageCounts),
        ];

        // Category Mix
        $stateCatCount = $apps->where('category', SubmissionWorkflow::CATEGORY_STATE)->count();
        $dirCatCount = $apps->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)->count();
        $cgisCatCount = $apps->filter(fn (Application $a) => $a->category === 'cgis_unit'
            || ! empty($a->user?->assigned_cgis_unit_code))->count();
        $categoryChart = [
            'labels' => ['State & Zonal Commands', 'Directorates', 'CGIS Units'],
            'data' => [$stateCatCount, $dirCatCount, $cgisCatCount],
        ];

        // Directorate Coverage (Direct Directorate returns + State/Zonal returns containing data for that directorate)
        $dirDirectCounts = [];
        $dirStateCoverageCounts = [];
        foreach (array_keys(self::DIRECTORATES) as $slug) {
            $dirDirectCounts[$slug] = 0;
            $dirStateCoverageCounts[$slug] = 0;
        }
        foreach ($apps as $app) {
            if ($app->category === SubmissionWorkflow::CATEGORY_DIRECTORATE) {
                $slug = strtolower((string) ($app->scope_code ?? $app->return_data['directorate_slug'] ?? ''));
                if (isset($dirDirectCounts[$slug])) {
                    $dirDirectCounts[$slug]++;
                }
            } elseif ($app->category === SubmissionWorkflow::CATEGORY_STATE && is_array($app->return_data)) {
                $keys = array_keys(array_filter(
                    $app->return_data,
                    fn ($v) => $v !== null && $v !== '' && $v !== [] && $v !== '0' && $v !== 0
                ));
                foreach (self::STATE_SECTION_PREFIXES as $slug => $prefixes) {
                    foreach ($keys as $key) {
                        $lk = strtolower((string) $key);
                        foreach ($prefixes as $prefix) {
                            if (str_starts_with($lk, $prefix)) {
                                $dirStateCoverageCounts[$slug]++;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        $directorateChart = [
            'labels' => collect(self::DIRECTORATES)->pluck('short')->values()->all(),
            'full_names' => collect(self::DIRECTORATES)->pluck('name')->values()->all(),
            'direct' => array_values($dirDirectCounts),
            'state_coverage' => array_values($dirStateCoverageCounts),
        ];

        // Zonal Matrix & Chart (ZONE-A through ZONE-H)
        $zonalMatrix = collect(self::ZONES)->map(function (string $label, string $zoneCode) use ($apps, $consolidatedReports) {
            $zoneApps = $apps->filter(function (Application $a) use ($zoneCode) {
                $z = strtoupper((string) ($a->zonal_code ?: $a->user?->assigned_zonal_command_code ?: ''));
                $s = strtoupper((string) ($a->scope_code ?? ''));

                return $z === $zoneCode || $s === $zoneCode;
            });
            $zTotal = $zoneApps->count();
            $zApproved = $zoneApps->filter(fn (Application $a) => strtolower((string) $a->status) === 'approved')->count();
            $zReturned = $zoneApps->filter(fn (Application $a) => in_array(strtolower((string) $a->status), ['returned', 'rejected', 'queried'], true))->count();
            $zPending = max(0, $zTotal - $zApproved - $zReturned);
            $zScopes = $zoneApps->pluck('scope_code')->filter()->map(fn ($c) => strtoupper((string) $c))->unique()->count();
            $zRate = $zTotal > 0 ? round(($zApproved / $zTotal) * 100, 1) : 0.0;

            $latestCon = $consolidatedReports
                ->first(fn (ConsolidatedReport $r) => strtoupper((string) $r->scope_code) === $zoneCode);

            return [
                'code' => $zoneCode,
                'name' => $label,
                'total' => $zTotal,
                'approved' => $zApproved,
                'pending' => $zPending,
                'returned' => $zReturned,
                'reporting_units' => $zScopes,
                'approval_rate' => $zRate,
                'consolidated_status' => $latestCon?->status,
                'consolidated_ref' => $latestCon?->reference,
            ];
        })->values();

        $zonalChart = [
            'labels' => $zonalMatrix->pluck('code')->all(),
            'approved' => $zonalMatrix->pluck('approved')->all(),
            'pending' => $zonalMatrix->pluck('pending')->all(),
            'returned' => $zonalMatrix->pluck('returned')->all(),
        ];

        // Formation Leaderboard (Top 15 by volume)
        $formationLeaderboard = $apps
            ->groupBy(fn (Application $a) => strtoupper((string) ($a->scope_code ?: 'UNASSIGNED')))
            ->map(function (Collection $group, string $scopeCode) {
                $first = $group->first();
                $resolvedName = $scopeCode !== 'UNASSIGNED'
                    ? (GeolocationService::stateNameFromCode($scopeCode)
                        ?? (self::DIRECTORATES[strtolower($scopeCode)]['name'] ?? null)
                        ?? (self::ZONES[$scopeCode] ?? $scopeCode))
                    : 'Unassigned';

                $gTotal = $group->count();
                $gApproved = $group->filter(fn (Application $a) => strtolower((string) $a->status) === 'approved')->count();
                $gReturned = $group->filter(fn (Application $a) => in_array(strtolower((string) $a->status), ['returned', 'rejected', 'queried'], true))->count();
                $gPending = max(0, $gTotal - $gApproved - $gReturned);

                return [
                    'scope_code' => $scopeCode,
                    'name' => $resolvedName,
                    'category' => ucfirst((string) ($first->category ?? 'state')),
                    'zone' => strtoupper((string) ($first->zonal_code ?? $first->user?->assigned_zonal_command_code ?? '-')),
                    'total' => $gTotal,
                    'approved' => $gApproved,
                    'pending' => $gPending,
                    'returned' => $gReturned,
                    'approval_rate' => $gTotal > 0 ? round(($gApproved / $gTotal) * 100, 1) : 0.0,
                    'last_submitted' => optional($group->max('created_at'))->format('d M Y, H:i') ?? '-',
                ];
            })
            ->sortByDesc('total')
            ->take(15)
            ->values();

        // Workflow Stage Turnaround & Bottleneck Analysis
        $stageSamples = [];
        $allTransitions = [];
        foreach ($apps as $application) {
            $entries = collect($application->workflow_path ?? []);
            for ($i = 1; $i < $entries->count(); $i++) {
                $from = $entries[$i - 1]['at'] ?? null;
                $to = $entries[$i]['at'] ?? null;
                $stage = $entries[$i]['stage'] ?? null;
                if ($from && $to && $stage) {
                    $hours = (strtotime((string) $to) - strtotime((string) $from)) / 3600;
                    if ($hours >= 0) {
                        $stageSamples[$stage][] = $hours;
                        $allTransitions[] = $hours;
                    }
                }
            }
        }

        $turnaround = collect($stageSamples)->map(function (array $hours, string $stage) {
            $avg = round(array_sum($hours) / count($hours), 1);
            $health = $avg <= 24 ? 'Fast' : ($avg <= 72 ? 'Moderate' : 'Bottleneck');

            return [
                'stage_key' => $stage,
                'stage' => self::STAGE_LABELS[$stage] ?? ucwords(str_replace('_', ' ', $stage)),
                'samples' => count($hours),
                'avg_hours' => $avg,
                'min_hours' => round(min($hours), 1),
                'max_hours' => round(max($hours), 1),
                'health' => $health,
            ];
        })->sortByDesc('avg_hours')->values();

        $avgTurnaroundHours = count($allTransitions) > 0
            ? round(array_sum($allTransitions) / count($allTransitions), 1)
            : null;

        // Available reporting periods for dropdown
        $availablePeriods = Application::query()
            ->whereNotNull('report_period')
            ->where('report_period', '!=', '')
            ->distinct()
            ->orderByDesc('report_period')
            ->pluck('report_period')
            ->filter(fn ($p) => is_string($p) && preg_match('/^\d{4}-\d{2}$/', $p))
            ->prepend($now->format('Y-m'))
            ->unique()
            ->values()
            ->all();

        return [
            'filters' => $cleanFilters,
            'availablePeriods' => $availablePeriods,
            'zones' => self::ZONES,
            'kpis' => [
                'total_returns' => $total,
                'approved_returns' => $approved,
                'pending_returns' => $pending,
                'returned_returns' => $returned,
                'awaiting_hq' => $awaitingHq,
                'approval_rate' => $approvalRate,
                'return_rate' => $returnRate,
                'active_formations' => $activeFormations,
                'consolidated_total' => $consolidatedCounts['total'],
                'consolidated_approved' => $consolidatedCounts['approved'],
                'consolidated_pending' => $consolidatedCounts['submitted'],
                'consolidated_stale' => $consolidatedCounts['stale'],
                'avg_turnaround_hours' => $avgTurnaroundHours,
                'current_month_returns' => $currentMonthReturns,
                'previous_month_returns' => $previousMonthReturns,
                'mom_delta_pct' => $momDeltaPct,
            ],
            'directorateChart' => $directorateChart,
            'statusChart' => $statusChart,
            'stageChart' => $stageChart,
            'categoryChart' => $categoryChart,
            'trendChart' => [
                'labels' => $trendLabels,
                'submitted' => $trendSubmitted,
                'approved' => $trendApproved,
                'returned' => $trendReturned,
                // Backward-compatible key for any consumers expecting `data`
                'data' => $trendSubmitted,
            ],
            'zonalChart' => $zonalChart,
            'zonalMatrix' => $zonalMatrix,
            'formationLeaderboard' => $formationLeaderboard,
            'turnaround' => $turnaround,
            'consolidatedCounts' => $consolidatedCounts,
            'recentConsolidated' => $consolidatedReports->take(8)->values(),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredApplicationsQuery(array $filters): Builder
    {
        $query = Application::query();

        if ($filters['period'] !== '') {
            $period = $filters['period'];
            $query->where(function (Builder $q) use ($period) {
                $q->where('report_period', $period)
                    ->orWhere('return_data->report_period', $period)
                    ->orWhere('return_data->period', $period);
            });
        } elseif ($filters['range'] !== 'all') {
            $since = match ($filters['range']) {
                'current_month' => Carbon::now()->startOfMonth(),
                '3m' => Carbon::now()->subMonths(3)->startOfDay(),
                '6m' => Carbon::now()->subMonths(6)->startOfDay(),
                '12m' => Carbon::now()->subMonths(12)->startOfDay(),
                default => null,
            };
            if ($since) {
                $query->where('created_at', '>=', $since);
            }
        }

        if ($filters['category'] !== '') {
            if ($filters['category'] === 'cgis_unit') {
                $query->where(function (Builder $q) {
                    $q->where('category', 'cgis_unit')
                        ->orWhereHas('user', fn (Builder $u) => $u->whereNotNull('assigned_cgis_unit_code')->where('assigned_cgis_unit_code', '!=', ''));
                });
            } else {
                $query->where('category', $filters['category']);
            }
        }

        if ($filters['zone'] !== '') {
            $zone = $filters['zone'];
            $query->where(function (Builder $q) use ($zone) {
                $q->where('zonal_code', $zone)
                    ->orWhere('scope_code', $zone)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('assigned_zonal_command_code', $zone));
            });
        }

        if ($filters['status'] !== '') {
            if ($filters['status'] === 'returned') {
                $query->whereIn('status', ['returned', 'rejected', 'queried']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        return $query;
    }
}
