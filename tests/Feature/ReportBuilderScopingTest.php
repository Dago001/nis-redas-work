<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\SettingService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportBuilderScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::set('abac_enabled', false, 'boolean');
    }

    private function makeUser(array $attrs): User
    {
        return User::create(array_merge([
            'name' => 'Officer',
            'email_verified_at' => now(),
            'password' => Hash::make('Secure@12'),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'geolocation_enabled' => false,
        ], $attrs));
    }

    public function test_report_builder_enforces_strict_role_scoping_across_all_tiers(): void
    {
        $lagosState = $this->makeUser([
            'name' => 'Lagos Officer',
            'service_number' => '85001',
            'email' => 'lagos85001@nis.gov.ng',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
        ]);

        $kanoState = $this->makeUser([
            'name' => 'Kano Officer',
            'service_number' => '85002',
            'email' => 'kano85002@nis.gov.ng',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'KNSC',
            'assigned_state_code' => 'KNSC',
            'assigned_zonal_command_code' => 'ZONE-B',
            'access_level' => 0,
        ]);

        $zoneAOfficer = $this->makeUser([
            'name' => 'Zone A Officer',
            'service_number' => '85003',
            'email' => 'zonea85003@nis.gov.ng',
            'role' => 'officer',
            'user_category' => 'zonal_officer',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
        ]);

        $zoneAAdmin = $this->makeUser([
            'name' => 'Zone A Admin',
            'service_number' => '85004',
            'email' => 'zoneaadmin85004@nis.gov.ng',
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 3,
        ]);

        $ictOfficer = $this->makeUser([
            'name' => 'ICT Officer',
            'service_number' => '85005',
            'email' => 'ict85005@nis.gov.ng',
            'role' => 'directorate',
            'user_category' => 'directorate_user',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'ict',
            'assigned_directorate_code' => 'ict',
            'assigned_section_code' => 'cybersecurity',
            'access_level' => 0,
        ]);

        $hrmOfficer = $this->makeUser([
            'name' => 'HRM Officer',
            'service_number' => '85006',
            'email' => 'hrm85006@nis.gov.ng',
            'role' => 'directorate',
            'user_category' => 'directorate_user',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'hrm',
            'assigned_directorate_code' => 'hrm',
            'access_level' => 0,
        ]);

        $servicomOfficer = $this->makeUser([
            'name' => 'SERVICOM Officer',
            'service_number' => '85007',
            'email' => 'servicom85007@nis.gov.ng',
            'role' => 'unit_officer',
            'user_category' => 'cgis_unit_user',
            'primary_location_type' => 'unit',
            'primary_location_code' => 'servicom',
            'assigned_cgis_unit_code' => 'servicom',
            'access_level' => 0,
        ]);

        $prsAdmin = $this->makeUser([
            'name' => 'PRS Admin',
            'service_number' => '85008',
            'email' => 'prs85008@nis.gov.ng',
            'role' => 'directorate',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'prs',
            'assigned_directorate_code' => 'prs',
            'access_level' => 2,
        ]);

        $superAdmin = $this->makeUser([
            'name' => 'Super Admin',
            'service_number' => '85009',
            'email' => 'super85009@nis.gov.ng',
            'role' => 'admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 6,
        ]);

        // Seed returns across every tier
        $appLagos = Application::create([
            'user_id' => $lagosState->id,
            'status' => 'approved',
            'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
            'category' => SubmissionWorkflow::CATEGORY_STATE,
            'report_period' => '2026-11',
            'scope_code' => 'LASC',
            'zonal_code' => 'ZONE-A',
            'return_data' => ['reporting_officer' => 'LAGOS_RETURN_MARKER', 'command_name' => 'LASC'],
        ]);

        $appKano = Application::create([
            'user_id' => $kanoState->id,
            'status' => 'approved',
            'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
            'category' => SubmissionWorkflow::CATEGORY_STATE,
            'report_period' => '2026-11',
            'scope_code' => 'KNSC',
            'zonal_code' => 'ZONE-B',
            'return_data' => ['reporting_officer' => 'KANO_RETURN_MARKER', 'command_name' => 'KNSC'],
        ]);

        $appZoneA = Application::create([
            'user_id' => $zoneAOfficer->id,
            'status' => 'approved',
            'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
            'category' => SubmissionWorkflow::CATEGORY_ZONE,
            'report_period' => '2026-11',
            'scope_code' => 'ZONE-A',
            'zonal_code' => 'ZONE-A',
            'return_data' => ['reporting_officer' => 'ZONEA_RETURN_MARKER', 'command_name' => 'ZONE-A'],
        ]);

        $appIct = Application::create([
            'user_id' => $ictOfficer->id,
            'status' => 'pending',
            'workflow_stage' => SubmissionWorkflow::STAGE_DIRECTORATE_REVIEW,
            'category' => SubmissionWorkflow::CATEGORY_DIRECTORATE,
            'report_period' => '2026-11',
            'scope_code' => 'ict',
            'section_code' => 'cybersecurity',
            'return_data' => ['reporting_officer' => 'ICT_RETURN_MARKER'],
        ]);

        $appHrm = Application::create([
            'user_id' => $hrmOfficer->id,
            'status' => 'approved',
            'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
            'category' => SubmissionWorkflow::CATEGORY_DIRECTORATE,
            'report_period' => '2026-11',
            'scope_code' => 'hrm',
            'return_data' => ['reporting_officer' => 'HRM_RETURN_MARKER'],
        ]);

        $appServicom = Application::create([
            'user_id' => $servicomOfficer->id,
            'status' => 'approved',
            'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
            'category' => SubmissionWorkflow::CATEGORY_CGIS,
            'report_period' => '2026-11',
            'scope_code' => 'servicom',
            'return_data' => ['reporting_officer' => 'SERVICOM_RETURN_MARKER'],
        ]);

        // 1. State Officer (LASC) -> sees ONLY LASC
        $this->actingAs($lagosState)->get(route('user.reports'))
            ->assertOk()
            ->assertSee('LAGOS_RETURN_MARKER')
            ->assertDontSee('KANO_RETURN_MARKER')
            ->assertDontSee('ZONEA_RETURN_MARKER')
            ->assertDontSee('ICT_RETURN_MARKER')
            ->assertDontSee('SERVICOM_RETURN_MARKER');

        // 2. Zonal Admin & Zonal Officer (ZONE-A) -> see ZONE-A + LASC, NOT KNSC/ICT/SERVICOM
        foreach ([$zoneAAdmin, $zoneAOfficer] as $zUser) {
            $this->actingAs($zUser)->get(route('user.reports'))
                ->assertOk()
                ->assertSee('LAGOS_RETURN_MARKER')
                ->assertSee('ZONEA_RETURN_MARKER')
                ->assertDontSee('KANO_RETURN_MARKER')
                ->assertDontSee('ICT_RETURN_MARKER')
                ->assertDontSee('SERVICOM_RETURN_MARKER');
        }

        // 3. Directorate Officer (ICT) -> sees ONLY ICT, NOT HRM/LASC/SERVICOM
        $this->actingAs($ictOfficer)->get(route('user.reports'))
            ->assertOk()
            ->assertSee('ICT_RETURN_MARKER')
            ->assertDontSee('HRM_RETURN_MARKER')
            ->assertDontSee('LAGOS_RETURN_MARKER')
            ->assertDontSee('SERVICOM_RETURN_MARKER');

        // 4. CGIS Unit Officer (SERVICOM) -> sees ONLY SERVICOM
        $this->actingAs($servicomOfficer)->get(route('user.reports'))
            ->assertOk()
            ->assertSee('SERVICOM_RETURN_MARKER')
            ->assertDontSee('LAGOS_RETURN_MARKER')
            ->assertDontSee('ICT_RETURN_MARKER');

        // 5. PRS Admin & Super Admin -> see ALL tiers and ZERO AI-generic badges
        foreach ([$prsAdmin, $superAdmin] as $natUser) {
            $this->actingAs($natUser)->get(route('user.reports'))
                ->assertOk()
                ->assertSee('LAGOS_RETURN_MARKER')
                ->assertSee('KANO_RETURN_MARKER')
                ->assertSee('ZONEA_RETURN_MARKER')
                ->assertSee('ICT_RETURN_MARKER')
                ->assertSee('HRM_RETURN_MARKER')
                ->assertSee('SERVICOM_RETURN_MARKER')
                ->assertDontSee('PRS SUPER ACCESS')
                ->assertDontSee('PRS Super & Comprehensive');
        }
    }
}
