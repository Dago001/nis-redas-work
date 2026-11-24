<?php

namespace App\Services\Reporting;

use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\ReportingUnit;
use App\Services\SubmissionWorkflow;
use Illuminate\Support\Collection;

class CompletenessService
{
    /**
     * Compute Option C reporting completeness for a reporting period and optional filters.
     * Consolidated reports are tracked separately and NEVER counted as replacements for original reports.
     *
     * @param  array{zone?:?string,directorate?:?string,formation?:?string,report_type?:?string,status?:?string,approval?:?string}  $filters
     * @return array{
     *   period:string,
     *   totals:array<string,mixed>,
     *   zones:Collection<int,array<string,mixed>>,
     *   directorates:Collection<int,array<string,mixed>>,
     *   formations:Collection<int,array<string,mixed>>
     * }
     */
    public static function summary(string $period, array $filters = []): array
    {
        $zoneFilter = $filters['zone'] ?? null;
        $directorateFilter = $filters['directorate'] ?? null;
        $formationFilter = $filters['formation'] ?? null;

        $units = ReportingUnit::query()->where('is_active', true)->get();
        $unitsById = $units->keyBy('id');

        $zones = $units->where('type', 'zone')
            ->when($zoneFilter, fn (Collection $c) => $c->where('code', $zoneFilter))
            ->sortBy('code')
            ->values();

        $formations = $units->where('type', 'formation')
            ->when($formationFilter, fn (Collection $c) => $c->where('code', $formationFilter))
            ->when($zoneFilter, function (Collection $c) use ($zones) {
                $zoneIds = $zones->pluck('id')->all();

                return $c->whereIn('parent_id', $zoneIds);
            })
            ->sortBy('name')
            ->values();

        $directorates = $units->where('type', 'directorate')
            ->when($directorateFilter, fn (Collection $c) => $c->where('code', $directorateFilter))
            ->sortBy('name')
            ->values();

        $sectionsByDirectorateId = $units->where('type', 'section')->groupBy('parent_id');

        // Single grouped query for original applications in the period
        $originalsQuery = Application::query()
            ->where('report_type', 'original')
            ->where('report_period', $period);

        if ($zoneFilter) {
            $formationCodesInZone = $formations->pluck('code')->all();
            $originalsQuery->where(function ($q) use ($zoneFilter, $formationCodesInZone): void {
                $q->where('zonal_code', $zoneFilter);
                if ($formationCodesInZone !== []) {
                    $q->orWhere(function ($inner) use ($formationCodesInZone): void {
                        $inner->where('category', SubmissionWorkflow::CATEGORY_STATE)
                            ->whereIn('scope_code', $formationCodesInZone);
                    });
                }
            });
        }

        if ($directorateFilter) {
            $originalsQuery->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)
                ->where('scope_code', $directorateFilter);
        }

        if ($formationFilter) {
            $originalsQuery->where('category', SubmissionWorkflow::CATEGORY_STATE)
                ->where('scope_code', $formationFilter);
        }

        $originals = $originalsQuery
            ->select(['id', 'category', 'scope_code', 'section_code', 'zonal_code', 'status', 'workflow_stage'])
            ->get();

        // Consolidated reports in the period
        $consolidatedQuery = ConsolidatedReport::query()->where('report_period', $period);
        if ($zoneFilter) {
            $consolidatedQuery->where('level', ConsolidatedReport::LEVEL_ZONAL)->where('scope_code', $zoneFilter);
        }
        if ($directorateFilter) {
            $consolidatedQuery->where('level', ConsolidatedReport::LEVEL_DIRECTORATE)->where('scope_code', $directorateFilter);
        }
        if ($formationFilter) {
            $parentId = $formations->first()?->parent_id;
            $parentZoneCode = $parentId ? $unitsById->get($parentId)?->code : null;
            if ($parentZoneCode) {
                $consolidatedQuery->where('level', ConsolidatedReport::LEVEL_ZONAL)->where('scope_code', $parentZoneCode);
            } else {
                $consolidatedQuery->whereRaw('1 = 0');
            }
        }
        $consolidated = $consolidatedQuery->get();

        // Formation-level status
        $stateOriginalsByScope = $originals->where('category', SubmissionWorkflow::CATEGORY_STATE)->groupBy('scope_code');

        $formationRows = $formations->map(function (ReportingUnit $unit) use ($stateOriginalsByScope, $unitsById) {
            /** @var Collection<int, Application> $apps */
            $apps = $stateOriginalsByScope->get($unit->code, collect());
            $parentZone = $unit->parent_id ? $unitsById->get($unit->parent_id) : null;

            $status = 'none';
            if ($apps->isNotEmpty()) {
                if ($apps->contains('status', 'approved')) {
                    $status = 'approved';
                } elseif ($apps->contains('status', 'returned')) {
                    $status = 'returned';
                } else {
                    $status = 'pending';
                }
            }

            return [
                'code' => $unit->code,
                'name' => $unit->name,
                'formation_type' => $unit->formation_type,
                'zone_code' => $parentZone?->code,
                'status' => $status,
                'total' => $apps->count(),
                'approved' => $apps->where('status', 'approved')->count(),
                'pending' => $apps->where('status', 'pending')->count(),
                'returned' => $apps->where('status', 'returned')->count(),
            ];
        });

        // Zone-level status
        $zonalConsolidatedByCode = $consolidated->where('level', ConsolidatedReport::LEVEL_ZONAL)->keyBy('scope_code');

        $zoneRows = $zones->map(function (ReportingUnit $zone) use ($formations, $stateOriginalsByScope, $zonalConsolidatedByCode) {
            $zoneFormations = $formations->where('parent_id', $zone->id);
            $expected = $zoneFormations->count();

            $receivedUnits = 0;
            $receivedOriginals = 0;
            $returned = 0;
            $approved = 0;
            $pending = 0;

            foreach ($zoneFormations as $f) {
                $apps = $stateOriginalsByScope->get($f->code, collect());
                if ($apps->isNotEmpty()) {
                    $receivedUnits++;
                    $receivedOriginals += $apps->count();
                    $returned += $apps->where('status', 'returned')->count();
                    $approved += $apps->where('status', 'approved')->count();
                    $pending += $apps->where('status', 'pending')->count();
                }
            }

            $outstanding = max(0, $expected - $receivedUnits);
            $completionPct = $expected > 0 ? round(($receivedUnits / $expected) * 100, 1) : null;
            /** @var ConsolidatedReport|null $con */
            $con = $zonalConsolidatedByCode->get($zone->code);

            return [
                'code' => $zone->code,
                'name' => $zone->name,
                'expected_formations' => $expected,
                'received_formations' => $receivedUnits,
                'received_originals' => $receivedOriginals,
                'outstanding' => $outstanding,
                'returned' => $returned,
                'approved' => $approved,
                'pending' => $pending,
                'completion_pct' => $completionPct,
                'consolidated_id' => $con?->id,
                'consolidated_status' => $con ? ($con->is_stale ? 'stale' : $con->status) : 'none',
                'consolidated_is_stale' => (bool) ($con?->is_stale ?? false),
            ];
        });

        // Directorate-level status
        $dirOriginalsByScope = $originals->where('category', SubmissionWorkflow::CATEGORY_DIRECTORATE)->groupBy('scope_code');
        $dirConsolidatedByCode = $consolidated->where('level', ConsolidatedReport::LEVEL_DIRECTORATE)->keyBy('scope_code');

        $directorateRows = $directorates->map(function (ReportingUnit $dir) use ($sectionsByDirectorateId, $dirOriginalsByScope, $dirConsolidatedByCode) {
            $sections = $sectionsByDirectorateId->get($dir->id, collect());
            $expectedSections = $sections->isNotEmpty() ? $sections->count() : null;

            /** @var Collection<int, Application> $apps */
            $apps = $dirOriginalsByScope->get($dir->code, collect());
            $receivedOriginals = $apps->count();

            $receivedSectionsCount = 0;
            if ($expectedSections !== null) {
                $submittedSectionCodes = $apps->pluck('section_code')->filter()->unique()->all();
                $receivedSectionsCount = $sections->whereIn('code', $submittedSectionCodes)->count();
            }

            $outstanding = $expectedSections !== null ? max(0, $expectedSections - $receivedSectionsCount) : null;
            $completionPct = ($expectedSections !== null && $expectedSections > 0)
                ? round(($receivedSectionsCount / $expectedSections) * 100, 1)
                : null;

            /** @var ConsolidatedReport|null $con */
            $con = $dirConsolidatedByCode->get($dir->code);

            return [
                'code' => $dir->code,
                'name' => $dir->name,
                'expected_sections' => $expectedSections,
                'received_sections' => $receivedSectionsCount,
                'received_originals' => $receivedOriginals,
                'outstanding' => $outstanding,
                'returned' => $apps->where('status', 'returned')->count(),
                'approved' => $apps->where('status', 'approved')->count(),
                'pending' => $apps->where('status', 'pending')->count(),
                'completion_pct' => $completionPct,
                'consolidated_id' => $con?->id,
                'consolidated_status' => $con ? ($con->is_stale ? 'stale' : $con->status) : 'none',
                'consolidated_is_stale' => (bool) ($con?->is_stale ?? false),
            ];
        });

        // Totals: Option C invariant — consolidated reports NEVER replace originals
        $expectedFormations = $directorateFilter ? 0 : $formations->whereNotNull('parent_id')->count();
        $expectedSectionsTotal = $formationFilter || $zoneFilter
            ? 0
            : $directorateRows->sum(fn ($d) => (int) ($d['expected_sections'] ?? 0));
        $expectedOriginals = $expectedFormations + $expectedSectionsTotal;

        $receivedOriginals = $originals->count();
        $receivedConsolidated = $consolidated->count();

        $receivedDistinctExpectedUnits = ($directorateFilter ? 0 : $formationRows->where('total', '>', 0)->whereNotNull('zone_code')->count())
            + ($formationFilter || $zoneFilter ? 0 : $directorateRows->sum('received_sections'));
        $outstandingTotal = max(0, $expectedOriginals - $receivedDistinctExpectedUnits);

        $returnedTotal = $originals->where('status', 'returned')->count()
            + $consolidated->where('status', ConsolidatedReport::STATUS_RETURNED)->count();
        $approvedTotal = $originals->where('status', 'approved')->count()
            + $consolidated->where('status', ConsolidatedReport::STATUS_APPROVED)->count();
        $pendingApprovalTotal = $originals->where('status', 'pending')->count()
            + $consolidated->where('status', ConsolidatedReport::STATUS_SUBMITTED)->count();

        $completionPct = $expectedOriginals > 0
            ? round((min($receivedDistinctExpectedUnits, $expectedOriginals) / $expectedOriginals) * 100, 1)
            : null;

        return [
            'period' => $period,
            'totals' => [
                'expected_originals' => $expectedOriginals,
                'received_originals' => $receivedOriginals,
                'received_consolidated' => $receivedConsolidated,
                'outstanding' => $outstandingTotal,
                'returned' => $returnedTotal,
                'approved' => $approvedTotal,
                'pending_approval' => $pendingApprovalTotal,
                'completion_pct' => $completionPct,
                'prs_records_total' => $receivedOriginals + $receivedConsolidated,
            ],
            'zones' => $zoneRows,
            'directorates' => $directorateRows,
            'formations' => $formationRows,
        ];
    }
}
