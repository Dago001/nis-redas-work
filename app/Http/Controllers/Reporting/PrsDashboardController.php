<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\ReportIndicatorValue;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\ReportAccess;
use App\Services\Reporting\CompletenessService;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PrsDashboardController extends Controller
{
    private function layoutFor(User $user): string
    {
        if ($user->user_category === 'super_admin' || $user->role === 'super_admin') {
            return 'super-admin.layout';
        }

        return ReportAccess::isNationalViewer($user)
            ? 'admin.headquarters.layout'
            : 'desk-admin.layout';
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $period = (string) $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $filters = [
            'period' => $period,
            'zone' => $request->query('zone') ?: null,
            'directorate' => $request->query('directorate') ?: null,
            'formation' => $request->query('formation') ?: null,
            'report_type' => $request->query('report_type') ?: 'all',
            'status' => $request->query('status') ?: null,
            'approval' => $request->query('approval') ?: null,
        ];

        $summary = CompletenessService::summary($period, $filters);

        // Unified Option C register: originals and consolidated reports appear as SEPARATE rows
        $records = collect();

        if (in_array($filters['report_type'], ['all', 'original'], true)) {
            $origQuery = Application::query()
                ->with('user:id,name,service_number')
                ->where('report_type', 'original')
                ->where('report_period', $period);

            if ($filters['zone']) {
                $zoneFormations = ReportAccess::formationCodesForZone($filters['zone']);
                $origQuery->where(function ($q) use ($filters, $zoneFormations): void {
                    $q->where('zonal_code', $filters['zone']);
                    if ($zoneFormations !== []) {
                        $q->orWhere(fn ($inner) => $inner->where('category', SubmissionWorkflow::CATEGORY_STATE)->whereIn('scope_code', $zoneFormations));
                    }
                });
            }
            if ($filters['directorate']) {
                $origQuery->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
                    ->where('scope_code', $filters['directorate']);
            }
            if ($filters['formation']) {
                $origQuery->where('category', SubmissionWorkflow::CATEGORY_STATE)
                    ->where('scope_code', $filters['formation']);
            }
            if ($filters['status']) {
                $origQuery->where('status', $filters['status']);
            }
            if ($filters['approval']) {
                $origQuery->where('workflow_stage', $filters['approval']);
            }

            foreach ($origQuery->latest()->get() as $app) {
                $records->push([
                    'record_type' => 'original',
                    'id' => $app->id,
                    'reference' => 'RET-'.str_pad((string) $app->id, 5, '0', STR_PAD_LEFT),
                    'category' => ucfirst((string) $app->category),
                    'scope_code' => $app->section_code ? "{$app->scope_code} / {$app->section_code}" : ($app->scope_code ?? '-'),
                    'period' => $app->report_period,
                    'author' => $app->user?->name ?? 'N/A',
                    'status' => $app->status,
                    'stage' => $app->workflow_stage,
                    'version' => 'v'.max(1, (int) $app->current_version),
                    'is_stale' => false,
                    'updated_at' => $app->updated_at,
                    'url' => route('prs.originals.show', $app),
                ]);
            }
        }

        if (in_array($filters['report_type'], ['all', 'consolidated'], true)) {
            $conQuery = ConsolidatedReport::query()
                ->with('creator:id,name')
                ->where('report_period', $period);

            if ($filters['zone']) {
                $conQuery->where('level', ConsolidatedReport::LEVEL_ZONAL)->where('scope_code', $filters['zone']);
            }
            if ($filters['directorate']) {
                $conQuery->where('level', ConsolidatedReport::LEVEL_DIRECTORATE)->where('scope_code', $filters['directorate']);
            }
            if ($filters['status']) {
                $conQuery->where('status', $filters['status']);
            }
            if ($filters['approval']) {
                $conQuery->where('workflow_stage', $filters['approval']);
            }

            foreach ($conQuery->latest()->get() as $con) {
                $records->push([
                    'record_type' => 'consolidated',
                    'id' => $con->id,
                    'reference' => $con->reference,
                    'category' => ucfirst($con->level).' Consolidated',
                    'scope_code' => $con->scope_code,
                    'period' => $con->report_period,
                    'author' => $con->creator?->name ?? 'N/A',
                    'status' => $con->status,
                    'stage' => $con->workflow_stage,
                    'version' => 'Rev '.$con->current_revision_no,
                    'is_stale' => (bool) $con->is_stale,
                    'updated_at' => $con->updated_at,
                    'url' => route('prs.consolidated.show', $con),
                ]);
            }
        }

        $sorted = $records->sortByDesc('updated_at')->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $paginatedRecords = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $allUnits = ReportingUnit::query()->where('is_active', true)->orderBy('name')->get();

        return view('reporting.prs.dashboard', [
            'layout' => $this->layoutFor($user),
            'period' => $period,
            'filters' => $filters,
            'summary' => $summary,
            'records' => $paginatedRecords,
            'zoneOptions' => $allUnits->where('type', 'zone')->values(),
            'directorateOptions' => $allUnits->where('type', 'directorate')->values(),
            'formationOptions' => $allUnits->where('type', 'formation')->values(),
        ]);
    }

    public function showOriginal(Application $application): View
    {
        /** @var User $user */
        $user = Auth::user();

        $application->load([
            'user:id,name,service_number,email',
            'reviewComments.user',
            'versions',
            'consolidatedSources.revision.consolidatedReport',
        ]);

        return view('reporting.prs.original-show', [
            'layout' => $this->layoutFor($user),
            'application' => $application,
        ]);
    }

    public function showConsolidated(ConsolidatedReport $report): View
    {
        /** @var User $user */
        $user = Auth::user();

        $report->load([
            'creator:id,name',
            'submitter:id,name',
            'approver:id,name',
            'revisions.creator:id,name',
            'revisions.sources.application.user:id,name',
            'revisions.sources.reportVersion',
        ]);

        return view('reporting.prs.consolidated-show', [
            'layout' => $this->layoutFor($user),
            'report' => $report,
            'latest' => $report->revisions->first(),
        ]);
    }

    public function indicators(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $period = (string) $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $category = $request->query('category');

        $rows = ReportIndicatorValue::query()
            ->selectRaw('indicator_id, category, report_period, SUM(value) as total_value, COUNT(DISTINCT application_id) as report_count')
            ->with('indicator:id,code,name,section,unit,status')
            ->where('is_current', true)
            ->where('report_period', $period)
            ->when(is_string($category) && $category !== '', fn ($q) => $q->where('category', $category))
            ->groupBy('indicator_id', 'category', 'report_period')
            ->orderByDesc('total_value')
            ->paginate(30)
            ->withQueryString();

        return view('reporting.prs.indicators', [
            'layout' => $this->layoutFor($user),
            'period' => $period,
            'category' => $category,
            'rows' => $rows,
        ]);
    }
}
