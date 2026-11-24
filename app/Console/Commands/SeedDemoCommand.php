<?php

namespace App\Console\Commands;

use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedDemoCommand extends Command
{
    protected $signature = 'redas:dev:seed-demo';

    protected $description = 'Local/testing-only command to seed demo accounts with random passwords for local UI walkthroughs';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to run demo seeder outside local/testing environments.');

            return self::FAILURE;
        }

        // Disable ABAC geolocation locally for loopback walkthrough
        SettingService::set('abac_enabled', false, 'boolean');
        SettingService::set('abac_location_enforcement', false, 'boolean');
        SettingService::set('abac_hq_bypass', true, 'boolean');

        // Ensure ZONE-A -> LA hierarchy exists
        $zoneA = ReportingUnit::firstOrCreate(
            ['code' => 'ZONE-A'],
            ['name' => 'Lagos Zonal HQ', 'type' => 'zone', 'is_active' => true]
        );
        ReportingUnit::updateOrCreate(
            ['code' => 'LA'],
            ['name' => 'Lagos State Command', 'type' => 'formation', 'formation_type' => 'state_command', 'parent_id' => $zoneA->id, 'is_active' => true]
        );

        $accounts = [
            ['name' => 'Demo Lagos Officer', 'service_number' => 'NIS/DMO/0001', 'email' => 'demo.officer@nis.local', 'role' => 'officer', 'user_category' => 'state_user', 'primary_location_type' => 'state', 'primary_location_code' => 'LA', 'access_level' => 0],
            ['name' => 'Demo Lagos Desk Admin', 'service_number' => 'NIS/DMO/0002', 'email' => 'demo.desk@nis.local', 'role' => 'state', 'user_category' => 'desk_admin', 'primary_location_type' => 'state', 'primary_location_code' => 'LA', 'assigned_zonal_command_code' => 'ZONE-A', 'access_level' => 1],
            ['name' => 'Demo Zone A Commander', 'service_number' => 'NIS/DMO/0003', 'email' => 'demo.zonal@nis.local', 'role' => 'zonal', 'user_category' => 'zonal_commander', 'primary_location_type' => 'zonal', 'primary_location_code' => 'ZONE-A', 'assigned_zonal_command_code' => 'ZONE-A', 'access_level' => 4],
            ['name' => 'Demo HQ Admin', 'service_number' => 'NIS/DMO/0004', 'email' => 'demo.hq@nis.local', 'role' => 'admin', 'user_category' => 'hq_admin', 'primary_location_type' => 'headquarters', 'primary_location_code' => 'HQ', 'access_level' => 5],
            ['name' => 'Demo PRS Admin', 'service_number' => 'NIS/DMO/0005', 'email' => 'demo.prs@nis.local', 'role' => 'admin', 'user_category' => 'directorate_admin', 'primary_location_type' => 'directorate', 'primary_location_code' => 'PRS', 'assigned_directorate_code' => 'PRS', 'access_level' => 3],
        ];

        $this->warn('LOCAL DEMO ACCOUNTS (random passwords generated for this session only):');

        foreach ($accounts as $acct) {
            $password = Str::password(16);
            $user = User::updateOrCreate(
                ['email' => $acct['email']],
                array_merge($acct, [
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'is_enabled' => true,
                    'must_change_password' => false,
                ])
            );
            $this->line(sprintf('%-24s | %-24s | %s', $user->user_category, $user->email, $password));
        }

        return self::SUCCESS;
    }
}
