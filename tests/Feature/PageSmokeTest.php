<?php

namespace Tests\Feature;

use App\Models\ConsolidatedReport;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\Reporting\ConsolidationService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_all_primary_role_pages_render_200_and_cross_role_pages_return_403(): void
    {
        $zone = ReportingUnit::create(['code' => 'ZONE-A', 'name' => 'Lagos Zonal HQ', 'type' => 'zone', 'is_active' => true]);
        ReportingUnit::create(['code' => 'LA', 'name' => 'Lagos', 'type' => 'formation', 'parent_id' => $zone->id, 'is_active' => true]);
        ReportingUnit::create(['code' => 'hrm', 'name' => 'HRM', 'type' => 'directorate', 'is_active' => true]);

        $stateUser = User::factory()->create(['role' => 'officer', 'user_category' => 'state_user', 'primary_location_type' => 'state', 'primary_location_code' => 'LA', 'access_level' => 0]);
        $deskAdmin = User::factory()->create(['role' => 'state', 'user_category' => 'desk_admin', 'primary_location_type' => 'state', 'primary_location_code' => 'LA', 'assigned_zonal_command_code' => 'ZONE-A', 'access_level' => 1]);
        $zonal = User::factory()->create(['role' => 'zonal', 'user_category' => 'zonal_commander', 'primary_location_type' => 'zonal', 'primary_location_code' => 'ZONE-A', 'access_level' => 4]);
        $dirUser = User::factory()->create(['role' => 'directorate', 'user_category' => 'directorate_user', 'primary_location_type' => 'directorate', 'primary_location_code' => 'HRM', 'assigned_directorate_code' => 'HRM', 'access_level' => 0]);
        $dirAdmin = User::factory()->create(['role' => 'admin', 'user_category' => 'directorate_admin', 'primary_location_type' => 'directorate', 'primary_location_code' => 'HRM', 'assigned_directorate_code' => 'HRM', 'access_level' => 3]);
        $prsAdmin = User::factory()->create(['role' => 'admin', 'user_category' => 'directorate_admin', 'primary_location_type' => 'directorate', 'primary_location_code' => 'PRS', 'assigned_directorate_code' => 'PRS', 'access_level' => 3]);
        $cgisUser = User::factory()->create(['role' => 'unit_officer', 'user_category' => 'cgis_unit_user', 'primary_location_type' => 'unit', 'primary_location_code' => 'actu', 'assigned_cgis_unit_code' => 'actu', 'access_level' => 0]);
        $cgisDesk = User::factory()->create(['role' => 'unit_admin', 'user_category' => 'cgis_desk_admin', 'primary_location_type' => 'unit', 'primary_location_code' => 'actu', 'assigned_cgis_unit_code' => 'actu', 'access_level' => 2]);
        $hqAdmin = User::factory()->create(['role' => 'admin', 'user_category' => 'hq_admin', 'primary_location_type' => 'headquarters', 'primary_location_code' => 'HQ', 'access_level' => 5]);
        $genAdmin = User::factory()->create(['role' => 'admin', 'user_category' => 'admin', 'primary_location_type' => 'headquarters', 'primary_location_code' => 'HQ', 'access_level' => 5]);
        $superAdmin = User::factory()->create(['role' => 'admin', 'user_category' => 'super_admin', 'primary_location_type' => 'headquarters', 'primary_location_code' => 'HQ', 'access_level' => 6]);

        $app = SubmissionWorkflow::create($stateUser, ['command_name' => 'Lagos', 'report_period' => '2026-11', 'total' => 10]);
        $con = ConsolidationService::createDraft($zonal, ConsolidatedReport::LEVEL_ZONAL, 'ZONE-A', '2026-11', [$app->id], [], 'Smoke test');

        // Authorized pages
        $this->actingAs($stateUser)->get(route('user.dashboard'))->assertOk();
        $this->actingAs($stateUser)->get(route('user.submissions'))->assertOk();
        $this->actingAs($stateUser)->get(route('user.archive'))->assertOk();
        $this->actingAs($stateUser)->get(route('user.reports'))->assertOk();
        $this->actingAs($deskAdmin)->get(route('user.desk.home'))->assertOk();
        $this->actingAs($zonal)->get(route('user.zonal.home'))->assertOk();
        $this->actingAs($zonal)->get(route('consolidated.index'))->assertOk();
        $this->actingAs($zonal)->get(route('consolidated.show', $con))->assertOk();
        $this->actingAs($dirUser)->get(route('user.directorates.dashboard'))->assertOk();
        $this->actingAs($dirAdmin)->get(route('consolidated.index'))->assertOk();
        $this->actingAs($prsAdmin)->get(route('prs.dashboard'))->assertOk();
        $this->actingAs($prsAdmin)->get(route('prs.indicators'))->assertOk();
        $this->actingAs($cgisUser)->get(route('user.cgis-units.dashboard'))->assertOk();
        $this->actingAs($cgisDesk)->get(route('user.desk.home'))->assertOk();
        $this->actingAs($hqAdmin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($hqAdmin)->get(route('admin.consolidated.index'))->assertOk();
        $this->actingAs($hqAdmin)->get(route('prs.dashboard'))->assertOk();
        $this->actingAs($genAdmin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($superAdmin)->get(route('superadmin.dashboard'))->assertOk();

        // Cross-role rejections
        $this->actingAs($stateUser)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($stateUser)->get(route('consolidated.index'))->assertForbidden();
        $this->actingAs($stateUser)->get(route('prs.dashboard'))->assertForbidden();
        $this->actingAs($dirAdmin)->get(route('prs.dashboard'))->assertForbidden();
        $this->actingAs($genAdmin)->get(route('admin.consolidated.index'))->assertForbidden();
    }

    public function test_every_super_admin_sidebar_link_returns_200(): void
    {
        $superAdmin = User::factory()->create([
            'service_number' => '35562',
            'role' => 'super_admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 6,
            'mfa_enabled' => true,
            'must_change_password' => false,
        ]);

        $sidebarRoutes = [
            'superadmin.dashboard',
            'superadmin.returns',
            'admin.dashboard',
            'prs.dashboard',
            'admin.consolidated.index',
            'admin.consolidation',
            'prs.indicators',
            'user.reports',
            'user.archive',
            'admin.users',
            'admin.users.create',
            'admin.audit-log.index',
            'admin.settings.index',
            'user.profile',
        ];

        foreach ($sidebarRoutes as $routeName) {
            $response = $this->actingAs($superAdmin)->get(route($routeName));
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Super Admin sidebar route [{$routeName}] returned {$response->getStatusCode()} instead of 200."
            );
            $response->assertSee('Super Admin Console');
            $response->assertSee('saNotifDot');
            $response->assertDontSee('ICT Admin');
        }

        // Verify Super Admin can create a new user with 5-digit Service No and strong password
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'CIS New Officer',
            'email' => 'new.officer@nis.gov.ng',
            'service_number' => '44556',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'is_enabled' => '1',
            'password' => 'StrongPass@1',
            'password_confirmation' => 'StrongPass@1',
        ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'service_number' => '44556',
            'email' => 'new.officer@nis.gov.ng',
            'must_change_password' => true,
        ]);
    }
}
