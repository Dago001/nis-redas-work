<?php

namespace Tests\Feature;

use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportBuilderScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::set('abac_enabled', false, 'boolean');
    }

    private function makeUser(array $overrides): User
    {
        return User::create(array_merge([
            'name' => 'Test Officer',
            'service_number' => (string) random_int(80000, 89999),
            'email' => 'officer.'.uniqid().'@nis.gov.ng',
            'email_verified_at' => now(),
            'password' => Hash::make('Secure@12'),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'geolocation_enabled' => false,
        ], $overrides));
    }

    public function test_state_command_officer_gets_command_scoped_report_builder(): void
    {
        $officer = $this->makeUser([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
        ]);

        $res = $this->actingAs($officer)->get(route('user.reports'));
        $res->assertOk();
        $res->assertSee('Report Builder');
        $res->assertSee('LASC');
        $res->assertDontSee('National Consolidated Summary');

        // Server-side ignores forged PRS filters for state officer
        $this->actingAs($officer)->post(route('user.reports.generate'), [
            'report_type' => 'submission_summary',
            'date_from' => now()->startOfYear()->toDateString(),
            'date_to' => now()->toDateString(),
            'sections' => ['hrm'],
            'format' => 'csv',
            'zone_code' => 'ZONE-B',
            'command_code' => 'KNSC',
            'directorate_code' => 'prs',
        ])->assertRedirect();

        $report = GeneratedReport::latest('id')->firstOrFail();
        $this->assertArrayNotHasKey('zone_code', $report->filters);
        $this->assertArrayNotHasKey('command_code', $report->filters);
        $this->assertArrayNotHasKey('directorate_code', $report->filters);
        $this->assertSame('state', $report->filters['scope_level']);
    }

    public function test_non_prs_directorate_officer_is_locked_to_own_directorate_section(): void
    {
        $hrmOfficer = $this->makeUser([
            'role' => 'directorate',
            'user_category' => 'directorate_user',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'hrm',
            'assigned_directorate_code' => 'hrm',
            'access_level' => 0,
        ]);

        $res = $this->actingAs($hrmOfficer)->get(route('user.reports'));
        $res->assertOk();
        $res->assertSee('Report Builder');
        $res->assertSee('HRM');
        $res->assertDontSee('name="sections[]" value="border"', false);
        $res->assertDontSee('name="sections[]" value="visa"', false);

        // Forging other sections still locks to ['hrm']
        $this->actingAs($hrmOfficer)->post(route('user.reports.generate'), [
            'report_type' => 'submission_summary',
            'date_from' => now()->startOfYear()->toDateString(),
            'date_to' => now()->toDateString(),
            'sections' => ['finance', 'visa'],
            'format' => 'csv',
        ])->assertRedirect();

        $report = GeneratedReport::latest('id')->firstOrFail();
        $this->assertSame(['hrm'], $report->filters['sections']);
    }

    public function test_cgis_unit_officer_is_locked_to_own_cgis_unit(): void
    {
        $cgisOfficer = $this->makeUser([
            'role' => 'unit_officer',
            'user_category' => 'cgis_unit_user',
            'primary_location_type' => 'unit',
            'primary_location_code' => 'servicom',
            'assigned_cgis_unit_code' => 'servicom',
            'access_level' => 0,
        ]);

        $res = $this->actingAs($cgisOfficer)->get(route('user.reports'));
        $res->assertOk();
        $res->assertSee('Report Builder');
        $res->assertSee('SERVICOM');
    }

    public function test_zonal_commander_only_sees_commands_under_own_zone(): void
    {
        $zoneA = $this->makeUser([
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 3,
        ]);

        $res = $this->actingAs($zoneA)->get(route('user.reports'));
        $res->assertOk();
        $res->assertSee('Report Builder');
        $res->assertSee('Zone A');
        $res->assertSee('LASC');
        $res->assertSee('MMIA');
        $res->assertDontSee('KNSC - Kano State Command');
    }

    public function test_prs_directorate_admin_gets_super_and_comprehensive_report_builder(): void
    {
        $prsAdmin = $this->makeUser([
            'role' => 'directorate',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'prs',
            'assigned_directorate_code' => 'prs',
            'access_level' => 2,
        ]);

        $res = $this->actingAs($prsAdmin)->get(route('user.reports'));
        $res->assertOk();
        $res->assertSee('Report Builder');
        $res->assertSee('National Consolidated Summary');
        $res->assertSee('ZONE-A');
        $res->assertSee('ZONE-H');
        $res->assertSee('FCTC');
        $res->assertSee('TRSC');

        // PRS can generate a National Consolidated Summary filtered by Zone & Status
        $this->actingAs($prsAdmin)->post(route('user.reports.generate'), [
            'report_type' => 'consolidated_summary',
            'date_from' => now()->startOfYear()->toDateString(),
            'date_to' => now()->toDateString(),
            'zone_code' => 'ZONE-A',
            'status_filter' => 'approved',
            'sections' => ['hrm', 'prs', 'passport'],
            'format' => 'csv',
        ])->assertRedirect();

        $report = GeneratedReport::latest('id')->firstOrFail();
        $this->assertSame('consolidated_summary', $report->report_type);
        $this->assertSame('ZONE-A', $report->filters['zone_code']);
        $this->assertSame('approved', $report->filters['status_filter']);
        $this->assertSame('national', $report->filters['scope_level']);
        $this->assertSame('completed', $report->status);
    }
}
