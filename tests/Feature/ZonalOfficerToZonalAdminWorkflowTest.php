<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Reporting\ConsolidationService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ZonalOfficerToZonalAdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_zonal_officer_to_zonal_admin_consolidation_to_hq_approval_workflow(): void
    {
        $zone = ReportingUnit::create([
            'code' => 'ZONE-A',
            'name' => 'Zone A HQ Ikeja',
            'type' => 'zone',
            'is_active' => true,
        ]);

        ReportingUnit::create([
            'code' => 'LA',
            'name' => 'Lagos State Command',
            'type' => 'formation',
            'formation_type' => 'state_command',
            'parent_id' => $zone->id,
            'is_active' => true,
        ]);

        // Super Admin provisions Zonal Officer via UserManagementController
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 6,
            'email_verified_at' => now(),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.users.store'), [
                'name' => 'Zonal Officer A',
                'rank' => 'CSI',
                'service_number' => '81001',
                'email' => 'zonal.officer.a@immigration.gov.ng',
                'user_category' => 'zonal_officer',
                'assigned_zonal_command_code' => 'ZONE-A',
                'password' => 'Secure@12',
                'password_confirmation' => 'Secure@12',
            ])
            ->assertRedirect();

        $zonalOfficer = User::where('service_number', '81001')->firstOrFail();
        $this->assertSame('zonal_officer', $zonalOfficer->user_category);
        $this->assertSame('zonal', $zonalOfficer->primary_location_type);
        $this->assertSame('ZONE-A', $zonalOfficer->primary_location_code);
        $this->assertSame('ZONE-A', $zonalOfficer->assigned_zonal_command_code);
        $zonalOfficer->forceFill(['must_change_password' => false])->save();

        $zonalAdmin = User::factory()->create([
            'name' => 'Zonal Admin A',
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 4,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $stateOfficer = User::factory()->create([
            'name' => 'Lagos State Officer',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'assigned_state_code' => 'LA',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $deskAdmin = User::factory()->create([
            'name' => 'Lagos Desk Admin',
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'assigned_state_code' => 'LA',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 1,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $hqAdmin = User::factory()->create([
            'name' => 'HQ Approver',
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 5,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        // Zonal Officer can access their officer dashboard & return form
        $this->actingAs($zonalOfficer)->get(route('user.dashboard'))->assertOk()->assertSee('ZONE-A Officer Dashboard');
        $this->actingAs($zonalOfficer)->get(route('user.returns.create'))->assertOk();

        // 1. Zonal Officer submits Zone HQ return -> goes straight to zonal_review & notifies Zonal Admin
        $zonalReturn = SubmissionWorkflow::create($zonalOfficer, [
            'command_name' => 'ZONE-A',
            'report_period' => '2026-11',
            'passport' => ['issued' => 50],
        ]);
        $this->assertSame(SubmissionWorkflow::STAGE_ZONAL_REVIEW, $zonalReturn->workflow_stage);
        $this->assertSame('ZONE-A', $zonalReturn->zonal_code);
        $this->assertTrue(
            UserNotification::where('user_id', $zonalAdmin->id)->where('tag', 'review')->exists()
        );

        // 2. State Officer submits State return -> Desk Admin approves -> routes to zonal_review
        $stateReturn = SubmissionWorkflow::create($stateOfficer, [
            'command_name' => 'Lagos State Command',
            'report_period' => '2026-11',
            'passport' => ['issued' => 120],
        ]);
        SubmissionWorkflow::approve($stateReturn, $deskAdmin);

        // 3. Zonal Admin sees BOTH returns in dashboard and in consolidation candidates
        $this->actingAs($zonalAdmin)->get(route('user.zonal.home'))
            ->assertOk()
            ->assertSee('Zonal Admin Dashboard')
            ->assertSee($zonalOfficer->name)
            ->assertSee($stateOfficer->name);

        $candidates = ConsolidationService::candidateSources(ConsolidatedReport::LEVEL_ZONAL, 'ZONE-A', '2026-11');
        $this->assertCount(2, $candidates);

        // 4. Zonal Admin consolidates both reports and forwards to HQ Admin
        $consolidated = ConsolidationService::createDraft(
            actor: $zonalAdmin,
            level: ConsolidatedReport::LEVEL_ZONAL,
            scopeCode: 'ZONE-A',
            period: '2026-11',
            includeIds: [$zonalReturn->id, $stateReturn->id],
            exclusions: [],
            narrative: 'Zone A combined HQ + Lagos State return.'
        );
        $this->assertEquals(170.0, $consolidated->latestRevision->aggregated_data['passport.issued']['total']);

        ConsolidationService::submit($consolidated, $zonalAdmin);

        // 5. HQ Admin approves -> consolidated & both underlying returns become Approved + officers notified
        ConsolidationService::approve($consolidated, $hqAdmin);

        $this->assertSame(ConsolidatedReport::STATUS_APPROVED, $consolidated->fresh()->status);
        $this->assertSame('approved', $zonalReturn->fresh()->status);
        $this->assertSame(SubmissionWorkflow::STAGE_APPROVED, $zonalReturn->fresh()->workflow_stage);
        $this->assertSame('approved', $stateReturn->fresh()->status);
        $this->assertSame(SubmissionWorkflow::STAGE_APPROVED, $stateReturn->fresh()->workflow_stage);

        $this->assertTrue(UserNotification::where('user_id', $zonalAdmin->id)->where('tag', 'approved')->exists());
        $this->assertTrue(UserNotification::where('user_id', $zonalOfficer->id)->where('tag', 'return')->exists());
        $this->assertTrue(UserNotification::where('user_id', $stateOfficer->id)->where('tag', 'return')->exists());
    }

    public function test_zonal_officer_form_includes_zone_hq_and_autofills_zonal_admin_approved_commands(): void
    {
        $stateOfficer = User::factory()->create([
            'name' => 'Lagos Officer',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $deskAdmin = User::factory()->create([
            'name' => 'Lagos Desk Admin',
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 1,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $zonalAdmin = User::factory()->create([
            'name' => 'Zone A Admin',
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 4,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $zonalOfficer = User::factory()->create([
            'name' => 'Zone A Officer',
            'role' => 'officer',
            'user_category' => 'zonal_officer',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
            'is_enabled' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);

        $period = now()->format('Y-m');
        $lascReturn = SubmissionWorkflow::create($stateOfficer, [
            'command_name' => 'Lagos State Command',
            'period' => $period,
            'report_period' => $period,
            'hrm' => [
                'zones' => [
                    'a' => [
                        'lasc' => [
                            'comptroller' => 3,
                            'superintendent' => 7,
                            'inspectorate' => 12,
                            'assistant' => 25,
                        ],
                    ],
                ],
            ],
            'breach' => [
                'ZONE_A' => [
                    'LASC' => [
                        'companies' => 4,
                        'expatriates' => 6,
                        'officers' => 1,
                        'nigerians' => 9,
                    ],
                ],
            ],
            'smuggling_of_migrants' => [
                'LASC' => [
                    'migrants_intercepted' => 14,
                    'smugglers_arrested' => 2,
                ],
            ],
        ]);

        // Desk Admin approves -> Zonal Admin approves (routes to hq_review)
        SubmissionWorkflow::approve($lascReturn, $deskAdmin);
        SubmissionWorkflow::approve($lascReturn->fresh(), $zonalAdmin);

        // Zonal Officer opens return form -> sees ZONE A HQ + LASC auto-filled with approved figures
        $res = $this->actingAs($zonalOfficer)->get(route('user.returns.create'));
        $res->assertOk()
            ->assertSee('Zonal Officer Portal')
            ->assertSee('Zonal Officer Return')
            ->assertSee('ZONE A HQ')
            ->assertSee('Approved')
            ->assertSee('value="25"', false)
            ->assertSee('value="14"', false);
    }
}
