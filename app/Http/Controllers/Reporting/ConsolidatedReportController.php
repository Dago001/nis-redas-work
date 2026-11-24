<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\User;
use App\Services\Reporting\ConsolidationService;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConsolidatedReportController extends Controller
{
    /**
     * Resolve the authenticated user's consolidation level and scope, or abort 403.
     *
     * @return array{0:string,1:string}
     */
    private function resolveLevelAndScope(User $user): array
    {
        if ($user->user_category === 'zonal_commander') {
            $scope = trim((string) ($user->primary_location_code ?: $user->assigned_zonal_command_code));
            abort_if($scope === '', 403, 'Your account has no provisioned zonal scope.');

            return [ConsolidatedReport::LEVEL_ZONAL, $scope];
        }

        if ($user->user_category === 'directorate_admin') {
            $slug = $user->directorateSlug();
            abort_if($slug === null, 403, 'Your account has no provisioned directorate scope.');

            return [ConsolidatedReport::LEVEL_DIRECTORATE, $slug];
        }

        abort(403, 'Unauthorized.');
    }

    private function assertOwnsScope(ConsolidatedReport $report, string $level, string $scope): void
    {
        abort_unless($report->level === $level && $report->scope_code === $scope, 403, 'This consolidated report is outside your scope.');
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);

        $period = (string) $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $expectedUnits = ConsolidationService::expectedUnits($level, $scope);
        $candidates = ConsolidationService::candidateSources($level, $scope, $period);

        $reports = ConsolidatedReport::query()
            ->with(['latestRevision', 'creator:id,name'])
            ->where('level', $level)
            ->where('scope_code', $scope)
            ->orderByDesc('report_period')
            ->paginate(20)
            ->withQueryString();

        return view('reporting.consolidated.index', [
            'level' => $level,
            'scope' => $scope,
            'period' => $period,
            'expectedUnits' => $expectedUnits,
            'candidates' => $candidates,
            'reports' => $reports,
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);

        $period = (string) $request->query('period', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $expectedUnits = ConsolidationService::expectedUnits($level, $scope);
        $candidates = ConsolidationService::candidateSources($level, $scope, $period);
        $comparison = $this->buildComparisonMatrix($candidates);

        return view('reporting.consolidated.create', [
            'level' => $level,
            'scope' => $scope,
            'period' => $period,
            'expectedUnits' => $expectedUnits,
            'candidates' => $candidates,
            'comparison' => $comparison,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);

        $validated = $request->validate([
            'report_period' => ['required', 'date_format:Y-m'],
            'title' => ['nullable', 'string', 'max:191'],
            'narrative' => ['nullable', 'string', 'max:20000'],
            'include_ids' => ['nullable', 'array'],
            'include_ids.*' => ['integer'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['nullable', 'string', 'max:500'],
        ]);

        $includeIds = array_map('intval', $validated['include_ids'] ?? []);
        $rawExclusions = $validated['exclusions'] ?? [];
        $exclusions = [];
        foreach ($rawExclusions as $appId => $reason) {
            if (! in_array((int) $appId, $includeIds, true) && is_string($reason) && trim($reason) !== '') {
                $exclusions[(int) $appId] = trim($reason);
            }
        }

        $report = ConsolidationService::createDraft(
            actor: $user,
            level: $level,
            scopeCode: $scope,
            period: $validated['report_period'],
            includeIds: $includeIds,
            exclusions: $exclusions,
            narrative: $validated['narrative'] ?? null,
            title: $validated['title'] ?? null
        );

        return redirect()
            ->route('consolidated.show', $report)
            ->with('status', "Consolidated report {$report->reference} draft created.");
    }

    public function show(ConsolidatedReport $report): View
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);
        $this->assertOwnsScope($report, $level, $scope);

        $report->load([
            'creator:id,name',
            'submitter:id,name',
            'approver:id,name',
            'revisions.creator:id,name',
            'revisions.sources.application.user:id,name',
            'revisions.sources.reportVersion',
        ]);

        return view('reporting.consolidated.show', [
            'report' => $report,
            'latest' => $report->revisions->first(),
            'canEdit' => in_array($report->status, [ConsolidatedReport::STATUS_DRAFT, ConsolidatedReport::STATUS_RETURNED], true) || $report->is_stale,
            'canSubmit' => in_array($report->status, [ConsolidatedReport::STATUS_DRAFT, ConsolidatedReport::STATUS_RETURNED], true) && ! $report->is_stale,
        ]);
    }

    public function edit(ConsolidatedReport $report): View
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);
        $this->assertOwnsScope($report, $level, $scope);

        abort_unless(
            in_array($report->status, [ConsolidatedReport::STATUS_DRAFT, ConsolidatedReport::STATUS_RETURNED], true) || $report->is_stale,
            403,
            'This consolidated report cannot be edited in its current state.'
        );

        $report->load(['latestRevision.sources']);
        $candidates = ConsolidationService::candidateSources($level, $scope, $report->report_period);
        $expectedUnits = ConsolidationService::expectedUnits($level, $scope);
        $comparison = $this->buildComparisonMatrix($candidates);

        $latest = $report->latestRevision;
        $includedIds = $latest?->sources->where('included', true)->pluck('application_id')->all() ?? [];
        $exclusions = $latest?->sources->where('included', false)->pluck('exclusion_reason', 'application_id')->all() ?? [];

        return view('reporting.consolidated.edit', [
            'report' => $report,
            'latest' => $latest,
            'candidates' => $candidates,
            'expectedUnits' => $expectedUnits,
            'comparison' => $comparison,
            'includedIds' => $includedIds,
            'exclusions' => $exclusions,
        ]);
    }

    public function update(Request $request, ConsolidatedReport $report): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);
        $this->assertOwnsScope($report, $level, $scope);

        $validated = $request->validate([
            'narrative' => ['nullable', 'string', 'max:20000'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'include_ids' => ['nullable', 'array'],
            'include_ids.*' => ['integer'],
            'exclusions' => ['nullable', 'array'],
            'exclusions.*' => ['nullable', 'string', 'max:500'],
        ]);

        $includeIds = array_map('intval', $validated['include_ids'] ?? []);
        $exclusions = [];
        foreach (($validated['exclusions'] ?? []) as $appId => $reason) {
            if (! in_array((int) $appId, $includeIds, true) && is_string($reason) && trim($reason) !== '') {
                $exclusions[(int) $appId] = trim($reason);
            }
        }

        $updated = ConsolidationService::revise(
            report: $report,
            actor: $user,
            includeIds: $includeIds,
            exclusions: $exclusions,
            narrative: $validated['narrative'] ?? null,
            changeNote: $validated['change_note'] ?? null
        );

        return redirect()
            ->route('consolidated.show', $updated)
            ->with('status', "Revision {$updated->current_revision_no} saved for {$updated->reference}.");
    }

    public function submit(ConsolidatedReport $report): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        [$level, $scope] = $this->resolveLevelAndScope($user);
        $this->assertOwnsScope($report, $level, $scope);

        $submitted = ConsolidationService::submit($report, $user);

        return redirect()
            ->route('consolidated.show', $submitted)
            ->with('status', "Consolidated report {$submitted->reference} submitted for HQ approval.");
    }

    public function original(Application $application): View
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->can('view', $application), 403);

        $application->load(['reviewComments.user', 'versions']);

        return view('desk-admin.preview', [
            'application' => $application,
            'canReview' => false,
            'approveRoute' => 'user.desk.home',
            'rejectRoute' => 'user.desk.home',
        ]);
    }

    /**
     * Build a side-by-side comparison matrix of top indicators across candidate source reports.
     *
     * @param  \Illuminate\Support\Collection<int, Application>  $candidates
     * @return array{indicators:array<string,string>,rows:array<int,array{application:Application,values:array<string,float>}>}
     */
    private function buildComparisonMatrix($candidates): array
    {
        $indicatorNames = [];
        $rows = [];

        foreach ($candidates as $app) {
            $values = [];
            foreach (($app->currentVersion?->indicatorValues ?? []) as $iv) {
                if (! $iv->indicator || $iv->indicator->status === 'excluded') {
                    continue;
                }
                $code = $iv->indicator->code;
                $indicatorNames[$code] = $iv->indicator->name;
                $values[$code] = (float) $iv->value;
            }
            $rows[] = ['application' => $app, 'values' => $values];
        }

        ksort($indicatorNames);
        $indicatorNames = array_slice($indicatorNames, 0, 25, true);

        return [
            'indicators' => $indicatorNames,
            'rows' => $rows,
        ];
    }
}
