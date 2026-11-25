<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportingUnitSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        // Headquarters
        $rows[] = [
            'code' => 'HQ',
            'name' => 'NIS Service Headquarters',
            'type' => 'headquarters',
            'formation_type' => null,
            'parent_id' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // 8 Zonal Headquarters (matching NewSeeder)
        $zones = [
            'ZONE-A' => 'Lagos Zonal HQ',
            'ZONE-B' => 'Kaduna Zonal HQ',
            'ZONE-C' => 'Bauchi Zonal HQ',
            'ZONE-D' => 'Minna Zonal HQ',
            'ZONE-E' => 'Owerri Zonal HQ',
            'ZONE-F' => 'Ibadan Zonal HQ',
            'ZONE-G' => 'Benin Zonal HQ',
            'ZONE-H' => 'Makurdi Zonal HQ',
        ];

        foreach ($zones as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'type' => 'zone',
                'formation_type' => null,
                'parent_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 37 State Commands (no invented state->zone parent links; synced via redas:reporting-units:sync-zones)
        $states = [
            'AB' => 'Abia State Command',
            'AD' => 'Adamawa State Command',
            'AK' => 'Akwa Ibom State Command',
            'AN' => 'Anambra State Command',
            'BA' => 'Bauchi State Command',
            'BY' => 'Bayelsa State Command',
            'BE' => 'Benue State Command',
            'BO' => 'Borno State Command',
            'CR' => 'Cross River State Command',
            'DE' => 'Delta State Command',
            'EB' => 'Ebonyi State Command',
            'ED' => 'Edo State Command',
            'EK' => 'Ekiti State Command',
            'EN' => 'Enugu State Command',
            'FC' => 'FCT Command',
            'GO' => 'Gombe State Command',
            'IM' => 'Imo State Command',
            'JI' => 'Jigawa State Command',
            'KD' => 'Kaduna State Command',
            'KN' => 'Kano State Command',
            'KT' => 'Katsina State Command',
            'KE' => 'Kebbi State Command',
            'KO' => 'Kogi State Command',
            'KW' => 'Kwara State Command',
            'LA' => 'Lagos State Command',
            'NA' => 'Nasarawa State Command',
            'NI' => 'Niger State Command',
            'OG' => 'Ogun State Command',
            'ON' => 'Ondo State Command',
            'OS' => 'Osun State Command',
            'OY' => 'Oyo State Command',
            'PL' => 'Plateau State Command',
            'RI' => 'Rivers State Command',
            'SO' => 'Sokoto State Command',
            'TA' => 'Taraba State Command',
            'YO' => 'Yobe State Command',
            'ZA' => 'Zamfara State Command',
        ];

        foreach ($states as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'type' => 'formation',
                'formation_type' => 'state_command',
                'parent_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 10 Directorates (matching DashboardController::DIRECTORATES)
        $directorates = [
            'hrm' => 'Human Resources Management (HRM)',
            'prs' => 'Planning, Research and Statistics (PRS)',
            'finance' => 'Finance and Accounts (F/A)',
            'investigation' => 'Investigation and Compliance (I/C)',
            'passport' => 'Passport and Other Travel Documents (P/OTD)',
            'visa' => 'Visa and Residency (V/R)',
            'migration' => 'Migration Directorate',
            'border' => 'Border Management',
            'ict' => 'ICT/Cybersecurity Directorate',
            'works-logistics' => 'Works and Logistics',
        ];

        foreach ($directorates as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'type' => 'directorate',
                'formation_type' => null,
                'parent_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 7 CGIS Units
        $cgisUnits = [
            'actu' => 'Anti-Corruption and Transparency Unit (ACTU)',
            'epms' => 'Electronic Passport Management System (EPMS)',
            'hostmanship' => 'Hostmanship Unit',
            'pro-media' => 'Public Relations / Media Unit',
            'protocol' => 'Protocol Unit',
            'provost' => 'Provost Unit',
            'servicom' => 'SERVICOM Unit',
        ];

        foreach ($cgisUnits as $code => $name) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'type' => 'cgis_unit',
                'formation_type' => null,
                'parent_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('reporting_units')->upsert(
            $rows,
            ['code'],
            ['name', 'type', 'formation_type', 'is_active', 'updated_at']
        );

        // Seed all 8 authoritative NIS Zones and 52 Commands from config/nis_hierarchy.php with exact parent_id links
        $hierarchy = (array) config('nis_hierarchy.zones', []);
        foreach ($hierarchy as $zoneCode => $zoneInfo) {
            DB::table('reporting_units')->updateOrInsert(
                ['code' => $zoneCode],
                [
                    'name' => $zoneInfo['name'] ?? $zoneCode,
                    'type' => 'zone',
                    'formation_type' => null,
                    'parent_id' => null,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $zoneId = DB::table('reporting_units')->where('code', $zoneCode)->value('id');

            foreach (($zoneInfo['commands'] ?? []) as $cmdCode => $cmdName) {
                $formationType = match (true) {
                    str_contains(strtolower($cmdName), 'airport') => 'airport',
                    str_contains(strtolower($cmdName), 'border') => 'border',
                    str_contains(strtolower($cmdName), 'marine') || str_contains(strtolower($cmdName), 'seaport') => 'marine',
                    str_contains(strtolower($cmdName), 'passport') => 'passport',
                    str_contains(strtolower($cmdName), 'training') || str_contains(strtolower($cmdName), 'college') || str_contains(strtolower($cmdName), 'academ') => 'training',
                    default => 'state',
                };

                DB::table('reporting_units')->updateOrInsert(
                    ['code' => $cmdCode],
                    [
                        'name' => $cmdName,
                        'type' => 'formation',
                        'formation_type' => $formationType,
                        'parent_id' => $zoneId,
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        // Also link 2-letter state codes to their official Zone parent
        foreach ((array) config('nis_hierarchy.state_to_zone', []) as $stateCode => $zoneCode) {
            $zoneId = DB::table('reporting_units')->where('code', $zoneCode)->value('id');
            if ($zoneId) {
                DB::table('reporting_units')->where('code', $stateCode)->update(['parent_id' => $zoneId, 'updated_at' => $now]);
            }
        }
    }
}
