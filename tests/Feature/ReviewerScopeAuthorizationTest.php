<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewerScopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    private function officer(string $state = 'LA'): User
    {
        return User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => $state,
            'access_level' => 0,
        ]);
    }

    public function test_desk_admin_with_null_scope_sees_empty_queue_and_is_denied_all_actions(): void
    {
        Storage::fake(config('filesystems.default'));
        Storage::put('supporting-documents/state/doc.pdf', '%PDF-1.4 test');

        $officer = $this->officer('LA');
        $application = SubmissionWorkflow::create($officer, [
            'command_name' => 'Lagos',
            'report_period' => '2026-11',
            'attachments' => ['supporting-documents/state/doc.pdf'],
        ]);

        $unscopedDeskAdmin = User::factory()->create([
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => null,
            'assigned_state_code' => null,
            'access_level' => 1,
        ]);

        // Pending queue must be empty (deny-by-default)
        $this->assertSame(0, SubmissionWorkflow::pendingQueryForApprover($unscopedDeskAdmin)->count());

        $this->actingAs($unscopedDeskAdmin)->get(route('desk.admin.submissions.show', $application))->assertForbidden();
        $this->actingAs($unscopedDeskAdmin)->patch(route('desk.admin.submissions.approve', $application))->assertForbidden();
        $this->actingAs($unscopedDeskAdmin)->get(route('desk.admin.submissions.download', $application))->assertForbidden();
        $this->actingAs($unscopedDeskAdmin)->get(route('desk.admin.submissions.document', [$application, 'attachments', 0]))->assertForbidden();
        $this->actingAs($unscopedDeskAdmin)->get(route('user.submissions.pdf', $application))->assertForbidden();
    }

    public function test_cross_state_cross_zone_and_cross_directorate_access_are_rejected(): void
    {
        $lagosOfficer = $this->officer('LA');
        $lagosApp = SubmissionWorkflow::create($lagosOfficer, ['command_name' => 'Lagos', 'report_period' => '2026-11']);

        $kanoDesk = User::factory()->create([
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'KN',
            'access_level' => 1,
        ]);

        $this->actingAs($kanoDesk)->get(route('desk.admin.submissions.show', $lagosApp))->assertForbidden();
        $this->actingAs($kanoDesk)->patch(route('desk.admin.submissions.approve', $lagosApp))->assertForbidden();

        // Advance to ZONE-A and test ZONE-B rejection
        $lagosApp->update(['workflow_stage' => SubmissionWorkflow::STAGE_ZONAL_REVIEW, 'zonal_code' => 'ZONE-A']);
        $zoneB = User::factory()->create([
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-B',
            'access_level' => 4,
        ]);
        $this->actingAs($zoneB)->patch(route('zonal.submissions.approve', $lagosApp))->assertForbidden();

        // Cross-directorate rejection
        $hrmOfficer = User::factory()->create([
            'role' => 'directorate',
            'user_category' => 'directorate_user',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'HRM',
            'assigned_directorate_code' => 'HRM',
            'access_level' => 0,
        ]);
        $hrmApp = SubmissionWorkflow::create($hrmOfficer, ['report_period' => '2026-11']);

        $ictAdmin = User::factory()->create([
            'role' => 'admin',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'ICT',
            'assigned_directorate_code' => 'ICT',
            'access_level' => 3,
        ]);
        $this->actingAs($ictAdmin)->get(route('desk.admin.submissions.show', $hrmApp))->assertForbidden();
        $this->actingAs($ictAdmin)->patch(route('desk.admin.submissions.approve', $hrmApp))->assertForbidden();
    }

    public function test_self_approval_and_manipulated_owner_record_id_are_rejected(): void
    {
        $deskAdmin = User::factory()->create([
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 1,
        ]);

        // Even if an application is owned by the desk admin in their own scope, self-approval is blocked
        $ownApp = Application::create([
            'user_id' => $deskAdmin->id,
            'status' => 'pending',
            'workflow_stage' => SubmissionWorkflow::STAGE_DESK_REVIEW,
            'category' => 'state',
            'scope_code' => 'LA',
            'return_data' => ['report_period' => '2026-11'],
        ]);

        $this->actingAs($deskAdmin)->patch(route('desk.admin.submissions.approve', $ownApp))->assertForbidden();

        // Officer B cannot access Officer A's submission by manipulating record ID
        $officerA = $this->officer('LA');
        $officerB = $this->officer('LA');
        $appA = SubmissionWorkflow::create($officerA, ['report_period' => '2026-11']);

        $this->actingAs($officerB)->get(route('user.returns.show', $appA))->assertForbidden();
    }

    public function test_double_approval_returns_conflict_or_forbidden(): void
    {
        $officer = $this->officer('LA');
        $deskAdmin = User::factory()->create([
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 1,
        ]);

        $app = SubmissionWorkflow::create($officer, ['report_period' => '2026-11']);

        $this->actingAs($deskAdmin)->patch(route('desk.admin.submissions.approve', $app))->assertRedirect();
        $this->actingAs($deskAdmin)->patch(route('desk.admin.submissions.approve', $app))->assertForbidden();

        // Direct service call when already moved past desk_review throws 409 Conflict
        try {
            SubmissionWorkflow::approve($app, $deskAdmin);
            $this->fail('Expected 409 conflict on duplicate service approval');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }
}
