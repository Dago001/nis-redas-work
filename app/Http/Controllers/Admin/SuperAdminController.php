<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ConsolidatedReport;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Executive (super-admin) portal - strictly read-only visualization of all
 * returns across every formation, status and period.
 */
class SuperAdminController extends Controller
{
    private const DIRECTORATES = [
        'hrm' => 'HRM',
        'prs' => 'PRS',
        'finance' => 'Finance',
        'investigation' => 'Investigation',
        'passport' => 'Passport',
        'visa' => 'Visa',
        'migration' => 'Migration',
        'border' => 'Border',
        'ict' => 'ICT',
        'works-logistics' => 'Works & Logistics',
    ];

    private const STAGE_LABELS = [
        SubmissionWorkflow::STAGE_SUBMITTED => 'Submitted',
        SubmissionWorkflow::STAGE_DESK_REVIEW => 'Desk Admin Review',
        SubmissionWorkflow::STAGE_ZONAL_REVIEW => 'Zonal Review',
        SubmissionWorkflow::STAGE_DIRECTORATE_REVIEW => 'Directorate Admin Review',
        SubmissionWorkflow::STAGE_CGIS_DESK_REVIEW => 'CGIS Desk Review',
        SubmissionWorkflow::STAGE_HQ_REVIEW => 'HQ Admin Review',
        SubmissionWorkflow::STAGE_ADMIN_REVIEW => 'Final Approval',
        SubmissionWorkflow::STAGE_APPROVED => 'Approved',
    ];

    public function dashboard(): View
    {
        $statusCounts = Application::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'approved' => (int) ($statusCounts['approved'] ?? 0),
            'pending' => (int) ($statusCounts['pending'] ?? 0),
            'returned' => (int) ($statusCounts['returned'] ?? 0),
            'avg_turnaround_days' => $this->averageTurnaroundDays(),
        ];

        $statusChart = [
            'labels' => ['Pending', 'Approved', 'Returned'],
            'data' => [
                (int) ($statusCounts['pending'] ?? 0),
                (int) ($statusCounts['approved'] ?? 0),
                (int) ($statusCounts['returned'] ?? 0),
            ],
        ];

        $categoryCounts = Application::query()
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        $categoryChart = [
            'labels' => ['State Commands', 'Directorates', 'CGIS Units'],
            'data' => [
                (int) ($categoryCounts[SubmissionWorkflow::CATEGORY_STATE] ?? 0),
                (int) ($categoryCounts[SubmissionWorkflow::CATEGORY_DIRECTORATE] ?? 0),
                (int) ($categoryCounts[SubmissionWorkflow::CATEGORY_CGIS] ?? 0),
            ],
        ];

        $perDirectorate = Application::query()
            ->selectRaw('scope_code, count(*) as aggregate')
            ->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
            ->groupBy('scope_code')
            ->pluck('aggregate', 'scope_code');

        $directorateChart = [
            'labels' => array_values(self::DIRECTORATES),
            'data' => collect(array_keys(self::DIRECTORATES))
                ->map(fn (string $slug) => (int) ($perDirectorate[$slug] ?? 0))
                ->all(),
        ];

        $zoneCounts = Application::query()
            ->selectRaw('zonal_code, count(*) as aggregate')
            ->whereNotNull('zonal_code')
            ->where('zonal_code', '!=', '')
            ->groupBy('zonal_code')
            ->orderBy('zonal_code')
            ->pluck('aggregate', 'zonal_code');

        $zoneChart = [
            'labels' => $zoneCounts->keys()->all(),
            'data' => $zoneCounts->map(fn ($count) => (int) $count)->values()->all(),
        ];

        $stateCounts = Application::query()
            ->selectRaw('scope_code, count(*) as aggregate')
            ->where('category', SubmissionWorkflow::CATEGORY_STATE)
            ->whereNotNull('scope_code')
            ->where('scope_code', '!=', '')
            ->groupBy('scope_code')
            ->orderByDesc('aggregate')
            ->limit(10)
            ->pluck('aggregate', 'scope_code');

        $stateChart = [
            'labels' => $stateCounts->keys()->all(),
            'data' => $stateCounts->map(fn ($count) => (int) $count)->values()->all(),
        ];

        $trendLabels = [];
        $submissions = [];
        $approvals = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $trendLabels[] = $month->format('M Y');
            $submissions[] = Application::query()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $approvals[] = Application::query()
                ->where('status', 'approved')
                ->whereYear('updated_at', $month->year)
                ->whereMonth('updated_at', $month->month)
                ->count();
        }

        $latestApproved = Application::query()
            ->with('user:id,name,service_number,assigned_cgis_unit_code')
            ->where('status', 'approved')
            ->latest('updated_at')
            ->limit(15)
            ->get();

        // Identity, Security & Option C Governance metrics for Super Admin
        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_enabled', true)->count();
        $disabledUsers = $totalUsers - $activeUsers;
        $mfaEnabledUsers = User::query()->where('mfa_enabled', true)->count();
        $mfaRate = $totalUsers > 0 ? (int) round(($mfaEnabledUsers / $totalUsers) * 100) : 0;
        $pendingPasswordChanges = User::query()->where('must_change_password', true)->count();

        $securityAlerts24h = AuditLog::query()
            ->where('created_at', '>=', now()->subHours(24))
            ->whereIn('status', ['blocked', 'failure'])
            ->count();

        $consolidatedCounts = ConsolidatedReport::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $governance = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'disabled_users' => $disabledUsers,
            'mfa_enabled_users' => $mfaEnabledUsers,
            'mfa_rate' => $mfaRate,
            'pending_password_changes' => $pendingPasswordChanges,
            'security_alerts_24h' => $securityAlerts24h,
            'consolidated_total' => (int) $consolidatedCounts->sum(),
            'consolidated_approved' => (int) ($consolidatedCounts['approved'] ?? 0),
            'consolidated_submitted' => (int) ($consolidatedCounts['submitted'] ?? 0),
        ];

        $recentUsers = User::query()
            ->select(['id', 'name', 'service_number', 'email', 'role', 'user_category', 'mfa_enabled', 'is_enabled', 'must_change_password', 'created_at'])
            ->latest('id')
            ->limit(8)
            ->get();

        $recentAuditLogs = AuditLog::query()
            ->with('user:id,name,service_number')
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('super-admin.dashboard', [
            'stats' => $stats,
            'governance' => $governance,
            'recentUsers' => $recentUsers,
            'recentAuditLogs' => $recentAuditLogs,
            'statusChart' => $statusChart,
            'categoryChart' => $categoryChart,
            'directorateChart' => $directorateChart,
            'zoneChart' => $zoneChart,
            'stateChart' => $stateChart,
            'trendChart' => [
                'labels' => $trendLabels,
                'submissions' => $submissions,
                'approvals' => $approvals,
            ],
            'latestApproved' => $latestApproved,
        ]);
    }

    public function returns(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', 'in:state,directorate,cgis'],
            'status' => ['nullable', 'in:pending,approved,returned'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Application::query()->with('user:id,name,service_number,assigned_cgis_unit_code');

        if ($category = $filters['category'] ?? null) {
            $query->where('category', $category);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($from = $filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $to);
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

        return view('super-admin.returns', [
            'applications' => $applications,
            'filters' => $filters,
            'stageLabels' => self::STAGE_LABELS,
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
            'layout' => 'super-admin.layout',
            'backRoute' => 'superadmin.returns',
            'documentRoute' => 'superadmin.returns.document',
        ]);
    }

    /**
     * Average end-to-end turnaround (submission to final approval) in days,
     * derived from workflow_path timestamps.
     */
    private function averageTurnaroundDays(): ?float
    {
        $durations = [];

        foreach (Application::query()->where('status', 'approved')->whereNotNull('workflow_path')->cursor() as $application) {
            $entries = collect($application->workflow_path ?? []);
            $first = $entries->first()['at'] ?? null;
            $last = $entries->last()['at'] ?? null;

            if ($first && $last) {
                $days = (strtotime((string) $last) - strtotime((string) $first)) / 86400;
                if ($days >= 0) {
                    $durations[] = $days;
                }
            }
        }

        return $durations === [] ? null : round(array_sum($durations) / count($durations), 1);
    }
}
