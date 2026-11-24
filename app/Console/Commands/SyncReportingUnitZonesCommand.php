<?php

namespace App\Console\Commands;

use App\Models\ReportingUnit;
use App\Models\User;
use Illuminate\Console\Command;

class SyncReportingUnitZonesCommand extends Command
{
    protected $signature = 'redas:reporting-units:sync-zones {--force : Overwrite existing conflicting zone assignments}';

    protected $description = 'Assign state formations to zones based on enabled desk_admin provisioning';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $zones = ReportingUnit::query()->where('type', 'zone')->get()->keyBy('code');

        $admins = User::query()
            ->where('user_category', 'desk_admin')
            ->where('is_enabled', true)
            ->whereNotNull('assigned_zonal_command_code')
            ->get();

        $updated = 0;
        $conflicts = 0;

        foreach ($admins as $admin) {
            $stateCode = trim((string) ($admin->primary_location_code ?: $admin->assigned_state_code));
            $zoneCode = trim((string) $admin->assigned_zonal_command_code);

            if ($stateCode === '' || $zoneCode === '' || ! $zones->has($zoneCode)) {
                continue;
            }

            $formation = ReportingUnit::query()
                ->where('code', $stateCode)
                ->where('type', 'formation')
                ->first();

            if (! $formation) {
                continue;
            }

            $zoneId = $zones->get($zoneCode)->id;

            if ($formation->parent_id !== null && $formation->parent_id !== $zoneId && ! $force) {
                $this->warn("Conflict: formation [{$stateCode}] is already assigned to parent #{$formation->parent_id}; skipping zone [{$zoneCode}] (use --force to overwrite).");
                $conflicts++;
                continue;
            }

            if ($formation->parent_id !== $zoneId) {
                $formation->parent_id = $zoneId;
                $formation->save();
                $updated++;
            }
        }

        $this->info("Synced {$updated} formation-to-zone assignments ({$conflicts} conflicts skipped).");

        return self::SUCCESS;
    }
}
