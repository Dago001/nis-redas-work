<?php

namespace App\Services\Reporting;

use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\ConsolidatedReportRevision;
use App\Models\ConsolidatedReportSource;
use App\Models\ReportIndicatorValue;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AuditLogger;
use App\Services\ReportAccess;
use App\Services\SubmissionWorkflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConsolidationService
{
    /**
     * Candidate original submissions eligible for consolidation at a given level, scope, and period.
     *
     * @return Collection<int, Application>
     */
    public static function candidateSources(string $level, string $scopeCode, string $period): Collection
    {
        $query = Application::query()
            ->with(['user:id,name,service_number', 'currentVersion.indicatorValues.indicator'])
            ->where('report_type', 'original')
            ->where('report_period', $period);

        if ($level === ConsolidatedReport::LEVEL_ZONAL) {
            $formationCodes = ReportAccess::formationCodesForZone($scopeCode);

            $query->whereIn('category', [SubmissionWorkflow::CATEGORY_STATE, SubmissionWorkflow::CATEGORY_ZONE])
                ->where(function ($q) use ($scopeCode, $formationCodes): void {
                    $q->where('zonal_code', $scopeCode)
                        ->orWhere(function ($inner) use ($scopeCode): void {
                            $inner->whereNull('zonal_code')
                                ->where('scope_code', $scopeCode);
                        });
                    if ($formationCodes !== []) {
                        $q->orWhereIn('scope_code', $formationCodes);
                    }
                });
        } elseif ($level === ConsolidatedReport::LEVEL_DIRECTORATE) {
            $query->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
                ->where('scope_code', $scopeCode);
        } else {
            return collect();
        }

        return $query->orderBy('scope_code')->orderBy('id')->get();
    }

    /**
     * Active subordinate units expected to report under a zone or directorate.
     *
     * @return Collection<int, ReportingUnit>
     */
    public static function expectedUnits(string $level, string $scopeCode): Collection
    {
        $parentType = $level === ConsolidatedReport::LEVEL_ZONAL ? 'zone' : 'directorate';
        $childType = $level === ConsolidatedReport::LEVEL_ZONAL ? 'formation' : 'section';

        $parent = ReportingUnit::query()
            ->where('code', $scopeCode)
            ->where('type', $parentType)
            ->first();

        if (! $parent) {
            return collect();
        }

        return ReportingUnit::query()
            ->where('parent_id', $parent->id)
            ->where('type', $childType)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Compute reporting gaps (outstanding units, returned originals, excluded submissions).
     *
     * @param  Collection<int, ReportingUnit>  $expectedUnits
     * @param  Collection<int, Application>  $candidates
     * @param  array<int, string>  $exclusions
     * @return array{expected_configured:bool,outstanding_units:array<int,array{code:string,name:string}>,returned_submissions:array<int,array{id:int,scope_code:?string,section_code:?string}>,excluded_submissions:array<int,array{id:int,scope_code:?string,reason:string}>}
     */
    public static function computeGaps(string $level, Collection $expectedUnits, Collection $candidates, array $exclusions): array
    {
        $unitField = $level === ConsolidatedReport::LEVEL_ZONAL ? 'scope_code' : 'section_code';
        $submittedCodes = $candidates
            ->pluck($unitField)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->unique()
            ->all();

        $outstanding = $expectedUnits
            ->filter(fn (ReportingUnit $u) => ! in_array($u->code, $submittedCodes, true))
            ->map(fn (ReportingUnit $u) => ['code' => $u->code, 'name' => $u->name])
            ->values()
            ->all();

        $returned = $candidates
            ->filter(fn (Application $a) => strtolower((string) $a->status) === 'returned')
            ->map(fn (Application $a) => [
                'id' => $a->id,
                'scope_code' => $a->scope_code,
                'section_code' => $a->section_code,
            ])
            ->values()
            ->all();

        $excluded = [];
        foreach ($candidates as $app) {
            if (array_key_exists($app->id, $exclusions)) {
                $excluded[] = [
                    'id' => $app->id,
                    'scope_code' => $app->scope_code,
                    'reason' => (string) $exclusions[$app->id],
                ];
            }
        }

        return [
            'expected_configured' => $expectedUnits->isNotEmpty(),
            'outstanding_units' => $outstanding,
            'returned_submissions' => $returned,
            'excluded_submissions' => $excluded,
        ];
    }

    /**
     * Aggregate structured indicator values from the specific report versions included in consolidation.
     *
     * @param  Collection<int, Application>  $includedApplications
     * @return array<string, array{name:string,section:?string,total:float,sources_count:int}>
     */
    public static function aggregate(Collection $includedApplications): array
    {
        $versionIds = $includedApplications
            ->map(fn (Application $a) => $a->currentVersion?->id)
            ->filter()
            ->values()
            ->all();

        if ($versionIds === []) {
            return [];
        }

        $values = ReportIndicatorValue::query()
            ->with('indicator:id,code,name,section,status')
            ->whereIn('report_version_id', $versionIds)
            ->get();

        $aggregated = [];

        foreach ($values as $row) {
            $indicator = $row->indicator;
            if (! $indicator || $indicator->status === 'excluded') {
                continue;
            }

            $code = $indicator->code;
            if (! isset($aggregated[$code])) {
                $aggregated[$code] = [
                    'name' => $indicator->name,
                    'section' => $indicator->section,
                    'total' => 0.0,
                    'sources_count' => 0,
                ];
            }

            $aggregated[$code]['total'] = round($aggregated[$code]['total'] + (float) $row->value, 4);
            $aggregated[$code]['sources_count']++;
        }

        ksort($aggregated);

        return $aggregated;
    }

    /**
     * Create a brand-new consolidated report draft (revision 1) referencing immutable source versions.
     *
     * @param  list<int>  $includeIds
     * @param  array<int, string>  $exclusions  [application_id => reason]
     */
    public static function createDraft(
        User $actor,
        string $level,
        string $scopeCode,
        string $period,
        array $includeIds,
        array $exclusions = [],
        ?string $narrative = null,
        ?string $title = null
    ): ConsolidatedReport {
        $existing = ConsolidatedReport::query()
            ->where('level', $level)
            ->where('scope_code', $scopeCode)
            ->where('report_period', $period)
            ->first();

        if ($existing) {
            throw new HttpException(409, 'A consolidated report already exists for this scope and period; revise it instead.');
        }

        $candidates = self::candidateSources($level, $scopeCode, $period)->keyBy('id');
        $expectedUnits = self::expectedUnits($level, $scopeCode);

        $includeIds = array_values(array_unique(array_map('intval', $includeIds)));
        $normalizedExclusions = self::validateSelections($candidates, $includeIds, $exclusions);

        return DB::transaction(function () use (
            $actor,
            $level,
            $scopeCode,
            $period,
            $includeIds,
            $normalizedExclusions,
            $narrative,
            $title,
            $candidates,
            $expectedUnits
        ): ConsolidatedReport {
            $prefix = $level === ConsolidatedReport::LEVEL_ZONAL ? 'CON-ZN' : 'CON-DIR';
            $reference = strtoupper(sprintf('%s-%s-%s', $prefix, preg_replace('/[^A-Za-z0-9\-]/', '', $scopeCode), $period));

            $report = ConsolidatedReport::create([
                'reference' => $reference,
                'level' => $level,
                'scope_code' => $scopeCode,
                'report_period' => $period,
                'title' => $title ?: sprintf('%s Consolidated Report - %s (%s)', ucfirst($level), strtoupper($scopeCode), $period),
                'status' => ConsolidatedReport::STATUS_DRAFT,
                'workflow_stage' => 'draft',
                'current_revision_no' => 1,
                'is_stale' => false,
                'created_by' => $actor->id,
            ]);

            self::writeRevision(
                report: $report,
                revisionNo: 1,
                actor: $actor,
                candidates: $candidates,
                includeIds: $includeIds,
                exclusions: $normalizedExclusions,
                expectedUnits: $expectedUnits,
                narrative: $narrative,
                changeNote: 'Initial consolidated draft'
            );

            AuditLogger::log(
                user: $actor,
                action: 'consolidated.created',
                status: 'success',
                entityType: 'consolidated_report',
                entityId: $report->id,
                details: [
                    'reference' => $report->reference,
                    'level' => $level,
                    'scope_code' => $scopeCode,
                    'report_period' => $period,
                    'included_count' => count($includeIds),
                    'excluded_count' => count($normalizedExclusions),
                ]
            );

            return $report->fresh(['latestRevision.sources.application', 'latestRevision.sources.reportVersion']);
        });
    }

    /**
     * Create a new immutable revision of an existing consolidated report, preserving prior revisions.
     *
     * @param  list<int>  $includeIds
     * @param  array<int, string>  $exclusions
     */
    public static function revise(
        ConsolidatedReport $report,
        User $actor,
        array $includeIds,
        array $exclusions = [],
        ?string $narrative = null,
        ?string $changeNote = null
    ): ConsolidatedReport {
        return DB::transaction(function () use ($report, $actor, $includeIds, $exclusions, $narrative, $changeNote): ConsolidatedReport {
            /** @var ConsolidatedReport $locked */
            $locked = ConsolidatedReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            $allowedToRevise = in_array($locked->status, [ConsolidatedReport::STATUS_DRAFT, ConsolidatedReport::STATUS_RETURNED], true)
                || $locked->is_stale;

            if (! $allowedToRevise) {
                throw new HttpException(409, 'Only draft, returned, or stale consolidated reports can be revised.');
            }

            $candidates = self::candidateSources($locked->level, $locked->scope_code, $locked->report_period)->keyBy('id');
            $expectedUnits = self::expectedUnits($locked->level, $locked->scope_code);

            $includeIds = array_values(array_unique(array_map('intval', $includeIds)));
            $normalizedExclusions = self::validateSelections($candidates, $includeIds, $exclusions);

            $nextRevNo = $locked->current_revision_no + 1;

            self::writeRevision(
                report: $locked,
                revisionNo: $nextRevNo,
                actor: $actor,
                candidates: $candidates,
                includeIds: $includeIds,
                exclusions: $normalizedExclusions,
                expectedUnits: $expectedUnits,
                narrative: $narrative,
                changeNote: $changeNote ?: 'Revised with latest source report versions'
            );

            $locked->current_revision_no = $nextRevNo;
            $locked->is_stale = false;
            $locked->stale_reason = null;
            $locked->stale_flagged_at = null;
            $locked->returned_reason = null;

            // Preserves approved_revision_no if previously approved; moves working state back to draft
            if (in_array($locked->status, [ConsolidatedReport::STATUS_SUBMITTED, ConsolidatedReport::STATUS_APPROVED, ConsolidatedReport::STATUS_RETURNED], true)) {
                $locked->status = ConsolidatedReport::STATUS_DRAFT;
                $locked->workflow_stage = 'draft';
            }

            $locked->save();

            AuditLogger::log(
                user: $actor,
                action: 'consolidated.revised',
                status: 'success',
                entityType: 'consolidated_report',
                entityId: $locked->id,
                details: [
                    'reference' => $locked->reference,
                    'revision_no' => $nextRevNo,
                    'included_count' => count($includeIds),
                    'excluded_count' => count($normalizedExclusions),
                ]
            );

            return $locked->fresh(['latestRevision.sources.application', 'latestRevision.sources.reportVersion', 'revisions']);
        });
    }

    /**
     * Submit a consolidated report to HQ review (PRS receives it alongside original reports).
     */
    public static function submit(ConsolidatedReport $report, User $actor): ConsolidatedReport
    {
        return DB::transaction(function () use ($report, $actor): ConsolidatedReport {
            /** @var ConsolidatedReport $locked */
            $locked = ConsolidatedReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ConsolidatedReport::STATUS_DRAFT, ConsolidatedReport::STATUS_RETURNED], true)) {
                throw new HttpException(409, 'Only draft or returned consolidated reports can be submitted.');
            }

            if ($locked->is_stale) {
                throw new HttpException(409, 'This consolidated report is stale because a source report changed. Revise it before submitting.');
            }

            $locked->update([
                'status' => ConsolidatedReport::STATUS_SUBMITTED,
                'workflow_stage' => SubmissionWorkflow::STAGE_HQ_REVIEW,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'returned_reason' => null,
            ]);

            AuditLogger::log(
                user: $actor,
                action: 'consolidated.submitted',
                status: 'success',
                entityType: 'consolidated_report',
                entityId: $locked->id,
                details: [
                    'reference' => $locked->reference,
                    'revision_no' => $locked->current_revision_no,
                ]
            );

            DB::afterCommit(function () use ($locked): void {
                $hqAdmins = User::query()
                    ->where('is_enabled', true)
                    ->where('user_category', 'hq_admin')
                    ->get();

                foreach ($hqAdmins as $admin) {
                    UserNotification::create([
                        'user_id' => $admin->id,
                        'type' => 'workflow',
                        'title' => 'Consolidated report awaiting HQ approval',
                        'description' => "{$locked->reference} ({$locked->title}) has been submitted for HQ review.",
                        'tag' => 'consolidated',
                        'action_url' => route('admin.consolidated.show', $locked),
                        'payload_json' => ['consolidated_report_id' => $locked->id],
                    ]);
                }
            });

            return $locked->fresh();
        });
    }

    /**
     * Approve a submitted consolidated report at HQ.
     */
    public static function approve(ConsolidatedReport $report, User $hqActor): ConsolidatedReport
    {
        if ($hqActor->user_category !== 'hq_admin') {
            throw new HttpException(403, 'Only HQ administrators may approve consolidated reports.');
        }

        return DB::transaction(function () use ($report, $hqActor): ConsolidatedReport {
            /** @var ConsolidatedReport $locked */
            $locked = ConsolidatedReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ConsolidatedReport::STATUS_SUBMITTED) {
                throw new HttpException(409, 'Consolidated report is not awaiting HQ approval.');
            }

            $locked->update([
                'status' => ConsolidatedReport::STATUS_APPROVED,
                'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
                'approved_by' => $hqActor->id,
                'approved_at' => now(),
                'approved_revision_no' => $locked->current_revision_no,
            ]);

            /* Cascade HQ final approval to all included underlying returns that are still pending,
               so they immediately reflect as Approved across HQ / PRS / Super Admin reporting & officer dashboards. */
            $approvedOriginals = collect();
            $latestRevision = $locked->latestRevision()->with('sources.application.user')->first();
            if ($latestRevision) {
                foreach ($latestRevision->sources as $source) {
                    $orig = $source->application;
                    if (! $source->included || ! $orig || in_array(strtolower((string) $orig->status), ['approved', 'returned'], true)) {
                        continue;
                    }
                    $path = is_array($orig->workflow_path) ? $orig->workflow_path : [];
                    $path[] = [
                        'stage' => SubmissionWorkflow::STAGE_APPROVED,
                        'by' => $hqActor->id,
                        'at' => now()->toDateTimeString(),
                        'consolidated_report_id' => $locked->id,
                    ];
                    $orig->update([
                        'status' => 'approved',
                        'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
                        'workflow_path' => $path,
                    ]);
                    $approvedOriginals->push($orig);
                }
            }

            AuditLogger::log(
                user: $hqActor,
                action: 'consolidated.approved',
                status: 'success',
                entityType: 'consolidated_report',
                entityId: $locked->id,
                details: [
                    'reference' => $locked->reference,
                    'approved_revision_no' => $locked->current_revision_no,
                    'approved_originals_count' => $approvedOriginals->count(),
                ]
            );

            DB::afterCommit(function () use ($locked, $approvedOriginals): void {
                UserNotification::create([
                    'user_id' => $locked->created_by,
                    'type' => 'workflow',
                    'title' => 'Consolidated report approved',
                    'description' => "{$locked->reference} (Revision {$locked->current_revision_no}) has been approved by HQ.",
                    'tag' => 'approved',
                    'action_url' => route('consolidated.show', $locked),
                    'payload_json' => ['consolidated_report_id' => $locked->id],
                ]);

                foreach ($approvedOriginals as $orig) {
                    $ref = 'RET-' . str_pad((string) $orig->id, 5, '0', STR_PAD_LEFT);
                    SubmissionWorkflow::notifySubmitter(
                        $orig,
                        "Return Approved by HQ ({$ref})",
                        "Your return {$ref} was approved by HQ as part of consolidated report {$locked->reference}."
                    );
                }
            });

            return $locked->fresh();
        });
    }

    /**
     * Return a submitted consolidated report for revision.
     */
    public static function returnReport(ConsolidatedReport $report, User $hqActor, string $reason): ConsolidatedReport
    {
        if ($hqActor->user_category !== 'hq_admin') {
            throw new HttpException(403, 'Only HQ administrators may return consolidated reports.');
        }

        return DB::transaction(function () use ($report, $hqActor, $reason): ConsolidatedReport {
            /** @var ConsolidatedReport $locked */
            $locked = ConsolidatedReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ConsolidatedReport::STATUS_SUBMITTED) {
                throw new HttpException(409, 'Consolidated report is not awaiting HQ approval.');
            }

            $locked->update([
                'status' => ConsolidatedReport::STATUS_RETURNED,
                'workflow_stage' => 'draft',
                'returned_reason' => $reason,
            ]);

            AuditLogger::log(
                user: $hqActor,
                action: 'consolidated.returned',
                status: 'success',
                entityType: 'consolidated_report',
                entityId: $locked->id,
                details: [
                    'reference' => $locked->reference,
                    'revision_no' => $locked->current_revision_no,
                ]
            );

            DB::afterCommit(function () use ($locked, $reason): void {
                UserNotification::create([
                    'user_id' => $locked->created_by,
                    'type' => 'workflow',
                    'title' => 'Consolidated report returned for revision',
                    'description' => "{$locked->reference} was returned by HQ. Reason: {$reason}",
                    'tag' => 'returned',
                    'action_url' => route('consolidated.show', $locked),
                    'payload_json' => ['consolidated_report_id' => $locked->id],
                ]);
            });

            return $locked->fresh();
        });
    }

    /**
     * Flag any consolidated reports whose latest revision references an older version of $application.
     * Never mutates existing consolidated revisions or source snapshots.
     */
    public static function flagStaleSources(Application $application): int
    {
        $sources = ConsolidatedReportSource::query()
            ->with(['revision.consolidatedReport', 'reportVersion'])
            ->where('application_id', $application->id)
            ->get();

        $flaggedCount = 0;
        $ref = 'RET-'.str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);

        foreach ($sources as $source) {
            $revision = $source->revision;
            $report = $revision?->consolidatedReport;

            if (! $revision || ! $report) {
                continue;
            }

            // Only check the latest revision of each consolidated report
            if ($revision->revision_no !== $report->current_revision_no) {
                continue;
            }

            $usedVersionNo = (int) ($source->reportVersion?->version_no ?? 0);
            if ($usedVersionNo >= (int) $application->current_version) {
                continue;
            }

            $reason = "Source {$ref} changed to version {$application->current_version} after consolidation (used version {$usedVersionNo}).";

            $report->update([
                'is_stale' => true,
                'stale_reason' => $reason,
                'stale_flagged_at' => now(),
            ]);

            AuditLogger::log(
                user: null,
                action: 'consolidated.stale_flagged',
                status: 'warning',
                entityType: 'consolidated_report',
                entityId: $report->id,
                details: [
                    'reference' => $report->reference,
                    'application_id' => $application->id,
                    'used_version_no' => $usedVersionNo,
                    'current_version_no' => $application->current_version,
                ]
            );

            UserNotification::create([
                'user_id' => $report->created_by,
                'type' => 'warning',
                'title' => 'Consolidated report flagged as stale',
                'description' => "{$report->reference}: {$reason}",
                'tag' => 'stale',
                'action_url' => route('consolidated.show', $report),
                'payload_json' => [
                    'consolidated_report_id' => $report->id,
                    'application_id' => $application->id,
                ],
            ]);

            $flaggedCount++;
        }

        return $flaggedCount;
    }

    /**
     * @param  Collection<int, Application>  $candidates
     * @param  list<int>  $includeIds
     * @param  array<int, string>  $exclusions
     * @return array<int, string>
     */
    private static function validateSelections(Collection $candidates, array $includeIds, array $exclusions): array
    {
        foreach ($includeIds as $id) {
            if (! $candidates->has($id)) {
                throw new HttpException(422, "Source report #{$id} is not an eligible report for this scope and period.");
            }
        }

        $normalizedExclusions = [];
        foreach ($exclusions as $appId => $reason) {
            $id = (int) $appId;
            if (! $candidates->has($id) || in_array($id, $includeIds, true)) {
                continue;
            }
            $trimmed = trim((string) $reason);
            if ($trimmed === '') {
                throw new HttpException(422, "An exclusion reason is required for excluded source report #{$id}.");
            }
            $normalizedExclusions[$id] = $trimmed;
        }

        return $normalizedExclusions;
    }

    /**
     * @param  Collection<int, Application>  $candidates
     * @param  list<int>  $includeIds
     * @param  array<int, string>  $exclusions
     * @param  Collection<int, ReportingUnit>  $expectedUnits
     */
    private static function writeRevision(
        ConsolidatedReport $report,
        int $revisionNo,
        User $actor,
        Collection $candidates,
        array $includeIds,
        array $exclusions,
        Collection $expectedUnits,
        ?string $narrative,
        ?string $changeNote
    ): ConsolidatedReportRevision {
        $includedApps = $candidates->only($includeIds)->values();

        // Ensure every referenced application has a version snapshot
        foreach ($candidates as $app) {
            if ($app->currentVersion === null) {
                ReportVersioning::snapshot($app, $actor, 'backfill');
                $app->load('currentVersion.indicatorValues.indicator');
            }
        }

        $aggregated = self::aggregate($includedApps);
        $gaps = self::computeGaps($report->level, $expectedUnits, $candidates, $exclusions);

        $revision = ConsolidatedReportRevision::create([
            'consolidated_report_id' => $report->id,
            'revision_no' => $revisionNo,
            'narrative' => $narrative,
            'aggregated_data' => $aggregated,
            'gaps' => $gaps,
            'created_by' => $actor->id,
            'change_note' => $changeNote,
            'created_at' => now(),
        ]);

        foreach ($includeIds as $id) {
            /** @var Application $app */
            $app = $candidates->get($id);
            ConsolidatedReportSource::create([
                'revision_id' => $revision->id,
                'application_id' => $app->id,
                'report_version_id' => $app->currentVersion->id,
                'included' => true,
                'exclusion_reason' => null,
            ]);
        }

        foreach ($exclusions as $id => $reason) {
            /** @var Application $app */
            $app = $candidates->get($id);
            ConsolidatedReportSource::create([
                'revision_id' => $revision->id,
                'application_id' => $app->id,
                'report_version_id' => $app->currentVersion->id,
                'included' => false,
                'exclusion_reason' => $reason,
            ]);
        }

        return $revision;
    }
}
