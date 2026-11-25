<?php

namespace Tests\Feature;

use App\Models\ReportingUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create([
            'service_number' => '35562',
            'role' => 'super_admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 6,
            'mfa_enabled' => true,
            'must_change_password' => false,
        ]);
    }

    private function makeHqAdmin(): User
    {
        return User::factory()->create([
            'service_number' => '22001',
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 5,
            'mfa_enabled' => true,
            'must_change_password' => false,
        ]);
    }

    public function test_super_admin_can_provision_directorate_admin_with_officer_identity_and_auto_scope(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Musa',
            'first_name' => 'Ibrahim',
            'middle_name' => 'Danladi',
            'service_number' => '41234',
            'rank' => 'CIS',
            'email' => 'ibrahim.musa@immigration.gov.ng',
            'user_category' => 'directorate_admin',
            'assigned_directorate_code' => 'prs',
            'is_enabled' => '1',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
        ])->assertRedirect(route('admin.users'));

        $created = User::where('service_number', '41234')->firstOrFail();
        $this->assertSame('Musa Ibrahim Danladi', $created->name);
        $this->assertSame('Musa', $created->surname);
        $this->assertSame('Ibrahim', $created->first_name);
        $this->assertSame('Danladi', $created->middle_name);
        $this->assertSame('CIS', $created->rank);
        $this->assertSame('admin', $created->role);
        $this->assertSame('directorate', $created->primary_location_type);
        $this->assertSame('prs', $created->assigned_directorate_code);
        $this->assertTrue($created->must_change_password);
    }

    public function test_state_desk_admin_auto_links_parent_zonal_command(): void
    {
        $zone = ReportingUnit::create(['code' => 'ZONE-A', 'name' => 'Zone A Lagos', 'type' => 'zone', 'is_active' => true]);
        ReportingUnit::create(['code' => 'LA', 'name' => 'Lagos State', 'type' => 'formation', 'parent_id' => $zone->id, 'is_active' => true]);

        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Okafor',
            'first_name' => 'Chinedu',
            'service_number' => '5123',
            'rank' => 'CSI',
            'email' => 'chinedu.okafor@immigration.gov.ng',
            'user_category' => 'desk_admin',
            'assigned_state_code' => 'LA',
            'is_enabled' => '1',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
        ])->assertRedirect(route('admin.users'));

        $created = User::where('service_number', '5123')->firstOrFail();
        $this->assertSame('LA', $created->assigned_state_code);
        $this->assertSame('ZONE-A', $created->assigned_zonal_command_code);
    }

    public function test_super_admin_can_create_super_admin_but_hq_admin_is_blocked(): void
    {
        $hqAdmin = $this->makeHqAdmin();
        $superAdmin = $this->makeSuperAdmin();

        // Non-super HQ admin must be blocked from privilege escalation
        $this->actingAs($hqAdmin)->post(route('admin.users.store'), [
            'surname' => 'Attempt',
            'first_name' => 'Escalation',
            'service_number' => '99881',
            'rank' => 'DCG',
            'email' => 'escalate@immigration.gov.ng',
            'user_category' => 'super_admin',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
        ])->assertForbidden();

        // Super admin succeeds cleanly
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Bello',
            'first_name' => 'Amina',
            'service_number' => '99882',
            'rank' => 'CGIS',
            'email' => 'amina.bello@immigration.gov.ng',
            'user_category' => 'super_admin',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
        ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseHas('users', [
            'service_number' => '99882',
            'user_category' => 'super_admin',
            'rank' => 'CGIS',
            'access_level' => 6,
        ]);
    }

    public function test_create_user_page_renders_official_nis_ranks_directory_formations_and_super_admin_chrome(): void
    {
        $this->seed(\Database\Seeders\NisDirectorySeeder::class);
        $superAdmin = $this->makeSuperAdmin();

        $response = $this->actingAs($superAdmin)->get(route('admin.users.create'));
        $response->assertOk();

        // Uniform Super Admin layout + live notification bell
        $response->assertSee('Super Admin Console');
        $response->assertSee('saNotifDot');

        // Officer Identity fields
        $response->assertSee('Surname');
        $response->assertSee('First Name');
        $response->assertSee('Middle Name');
        $response->assertSee('Service No (Username)');
        $response->assertSee('NIS Rank');
        $response->assertSee('Email Address');

        // Official NIS ranks present; removed non-NIS inspector ranks absent
        $response->assertSee('Comptroller-General of Immigration Service (CGIS)');
        $response->assertSee('Inspector of Immigration (II)');
        $response->assertSee('Assistant Inspector of Immigration (AII)');
        $response->assertSee('Immigration Assistant III (IA III)');
        $response->assertDontSee('Chief Inspector of Immigration (CII)');
        $response->assertDontSee('Deputy Chief Inspector of Immigration (DCII)');
        $response->assertDontSee('Assistant Chief Inspector of Immigration (ACII)');
        $response->assertDontSee('Principal Inspector of Immigration (PII)');
        $response->assertDontSee('Senior Inspector of Immigration (SII)');

        // All 7 official NIS Directory formation groups present
        foreach ([
            'State Commands',
            'Airport Commands',
            'Border Control Posts',
            'Marine Commands',
            'Passport Issuing Centres',
            'Training Institutions',
            'Foreign Missions',
        ] as $groupLabel) {
            $response->assertSee($groupLabel);
        }

        // Password generator & live 5-rule checklist
        $response->assertSee('generatePwBtn');
        $response->assertSee('pwChecklist');
    }
}
