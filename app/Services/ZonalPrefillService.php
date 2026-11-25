<?php

namespace App\Services;

use App\Models\Application;
use App\Models\User;

/**
 * Extracts Zonal-Admin-approved subordinate command returns for a Zone and period
 * so a Zonal Officer's return form auto-fills approved command rows/columns while
 * the Zonal Officer inputs only their own ZONE X HQ row.
 */
class ZonalPrefillService
{
    /**
     * @return array{
     *   zone_code: ?string,
     *   zone_hq_label: ?string,
     *   approved_commands: list<string>,
     *   hrm_zones: array<string, array{comptroller:int,superintendent:int,inspectorate:int,assistant:int}>,
     *   investigation_breach: array<string, array{companies:int,expatriates:int,officers:int,nigerians:int}>,
     *   migration_som: array<string, array<string, int>>,
     *   migration_tip: array<string, array<string, int>>,
     * }
     */
    public static function forUser(?User $user, ?string $period = null): array
    {
        $empty = [
            'zone_code' => null,
            'zone_hq_label' => null,
            'approved_commands' => [],
            'hrm_zones' => [],
            'investigation_breach' => [],
            'migration_som' => [],
            'migration_tip' => [],
        ];

        if (! $user || ! in_array($user->user_category, ['zonal_officer', 'zonal_commander'], true)) {
            return $empty;
        }

        $rawZone = strtoupper(trim((string) ($user->assigned_zonal_command_code ?: $user->primary_location_code)));
        if (! preg_match('/^ZONE-?([A-H])$/', $rawZone, $m)) {
            return $empty;
        }

        $letter = $m[1];
        $zoneCode = 'ZONE-'.$letter;
        $zoneHqLabel = 'ZONE '.$letter.' HQ';
        $targetPeriod = $period ?: now()->format('Y-m');

        $zonesConfig = (array) config('nis_hierarchy.zones', []);
        $zoneCmdCodes = array_keys((array) ($zonesConfig[$zoneCode]['commands'] ?? []));

        // Fetch subordinate command returns in this Zone for this period that have been
        // approved by the Zonal Admin (workflow_stage past zonal_review or final approved).
        $apps = Application::query()
            ->where('category', SubmissionWorkflow::CATEGORY_STATE)
            ->where('status', '!=', 'returned')
            ->where(function ($q) use ($zoneCode): void {
                $q->whereNull('scope_code')->orWhere('scope_code', '!=', $zoneCode);
            })
            ->where(function ($q) use ($zoneCode, $zoneCmdCodes): void {
                $q->where('zonal_code', $zoneCode);
                if ($zoneCmdCodes !== []) {
                    $q->orWhereIn('scope_code', $zoneCmdCodes);
                }
            })
            ->where(function ($q) use ($targetPeriod): void {
                $q->where('report_period', $targetPeriod)
                    ->orWhere('return_data->report_period', $targetPeriod)
                    ->orWhere('return_data->period', $targetPeriod);
            })
            ->where(function ($q): void {
                $q->where('status', 'approved')
                    ->orWhereIn('workflow_stage', [
                        SubmissionWorkflow::STAGE_HQ_REVIEW,
                        SubmissionWorkflow::STAGE_APPROVED,
                    ])
                    ->orWhereHas('reviewComments', function ($c): void {
                        $c->where('action', 'approved')
                            ->where('stage', SubmissionWorkflow::STAGE_ZONAL_REVIEW);
                    });
            })
            ->latest('updated_at')
            ->get();

        $approvedCommands = [];
        $hrmZones = [];
        $invBreach = [];
        $migSom = [];
        $migTip = [];

        $somFields = [
            'migrants_intercepted', 'smugglers_arrested', 'cases_investigation',
            'cases_prosecution', 'smugglers_convicted', 'reunited_family',
            'referrals', 'repatriated_nigerians', 'repatriated_foreigners',
        ];
        $tipFields = [
            'victims_rescued', 'traffickers_arrested', 'victims_referred_naptip',
            'traffickers_referred_naptip', 'victims_reunited', 'cl_victims_naptip',
            'victims_repatriated',
        ];

        foreach ($apps as $app) {
            $cmdCode = strtoupper(trim((string) $app->scope_code));
            if ($cmdCode === '' || isset($approvedCommands[$cmdCode])) {
                continue;
            }
            $approvedCommands[$cmdCode] = true;
            $data = is_array($app->return_data) ? $app->return_data : [];

            // 1. HRM Zones (prefer explicit hrm.zones.<letter>.<cmd>, fallback to hrm.cadre totals)
            $cmdKey = strtolower(str_replace(' ', '_', $cmdCode));
            $zoneRow = $data['hrm']['zones'][strtolower($letter)][$cmdKey] ?? null;
            if (is_array($zoneRow) && self::hasAnyPositive($zoneRow, ['comptroller', 'superintendent', 'inspectorate', 'assistant'])) {
                $hrmZones[$cmdCode] = [
                    'comptroller' => (int) ($zoneRow['comptroller'] ?? 0),
                    'superintendent' => (int) ($zoneRow['superintendent'] ?? 0),
                    'inspectorate' => (int) ($zoneRow['inspectorate'] ?? 0),
                    'assistant' => (int) ($zoneRow['assistant'] ?? 0),
                ];
            } else {
                $cadre = is_array($data['hrm']['cadre'] ?? null) ? $data['hrm']['cadre'] : [];
                $hrmZones[$cmdCode] = [
                    'comptroller' => (int) ($cadre['comptroller']['male'] ?? 0) + (int) ($cadre['comptroller']['female'] ?? 0),
                    'superintendent' => (int) ($cadre['superintendent']['male'] ?? 0) + (int) ($cadre['superintendent']['female'] ?? 0),
                    'inspectorate' => (int) ($cadre['inspectorate']['male'] ?? 0) + (int) ($cadre['inspectorate']['female'] ?? 0),
                    'assistant' => (int) ($cadre['assistant']['male'] ?? 0) + (int) ($cadre['assistant']['female'] ?? 0),
                ];
            }

            // 2. Investigation Breach of Immigration Law
            $breachRow = $data['breach']['ZONE_'.$letter][$cmdCode] ?? null;
            if (is_array($breachRow)) {
                $invBreach[$cmdCode] = [
                    'companies' => (int) ($breachRow['companies'] ?? 0),
                    'expatriates' => (int) ($breachRow['expatriates'] ?? 0),
                    'officers' => (int) ($breachRow['officers'] ?? 0),
                    'nigerians' => (int) ($breachRow['nigerians'] ?? 0),
                ];
            }

            // 3. Migration SOM & TIP
            $somRow = $data['smuggling_of_migrants'][$cmdCode] ?? null;
            if (is_array($somRow)) {
                $row = [];
                foreach ($somFields as $f) {
                    $row[$f] = (int) ($somRow[$f] ?? 0);
                }
                $migSom[$cmdCode] = $row;
            }

            $tipRow = $data['trafficking_in_persons'][$cmdCode] ?? null;
            if (is_array($tipRow)) {
                $row = [];
                foreach ($tipFields as $f) {
                    $row[$f] = (int) ($tipRow[$f] ?? 0);
                }
                $migTip[$cmdCode] = $row;
            }
        }

        return [
            'zone_code' => $zoneCode,
            'zone_hq_label' => $zoneHqLabel,
            'approved_commands' => array_keys($approvedCommands),
            'hrm_zones' => $hrmZones,
            'investigation_breach' => $invBreach,
            'migration_som' => $migSom,
            'migration_tip' => $migTip,
        ];
    }

    private static function hasAnyPositive(array $row, array $keys): bool
    {
        foreach ($keys as $k) {
            if ((int) ($row[$k] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }
}
