<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\SettingService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowInAppNotificationsTest extends TestCase
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

    public function test_next_reviewer_receives_in_app_notification_at_every_stage(): void
    {
        $stateOfficer = $this->makeUser([
            'name' => 'Lagos State Officer',
            'service_number' => '91001',
            'email' => 'state91001@nis.gov.ng',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
        ]);

        $deskAdmin = $this->makeUser([
            'name' => 'Lagos Desk Admin',
            'service_number' => '91002',
            'email' => 'desk91002@nis.gov.ng',
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 1,
        ]);

        $zonalAdmin = $this->makeUser([
            'name' => 'Zone A Admin',
            'service_number' => '91003',
            'email' => 'zonal91003@nis.gov.ng',
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 3,
        ]);

        $hqAdmin = $this->makeUser([
            'name' => 'HQ Admin',
            'service_number' => '91004',
            'email' => 'hq91004@nis.gov.ng',
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 5,
        ]);

        $dirOfficer = $this->makeUser([
            'name' => 'ICT Officer',
            'service_number' => '91005',
            'email' => 'ict91005@nis.gov.ng',
            'role' => 'directorate',
            'user_category' => 'directorate_user',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'ict',
            'assigned_directorate_code' => 'ict',
            'access_level' => 0,
        ]);

        $dirAdmin = $this->makeUser([
            'name' => 'ICT Directorate Admin',
            'service_number' => '91006',
            'email' => 'ictadmin91006@nis.gov.ng',
            'role' => 'directorate',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'ict',
            'assigned_directorate_code' => 'ict',
            'access_level' => 2,
        ]);

        // 1. State Officer submits -> Desk Admin is notified with unread in-app notification & visible bell badge
        $stateApp = SubmissionWorkflow::create($stateOfficer, [
            'period' => '2026-11',
            'reporting_officer' => $stateOfficer->name,
            'command_name' => 'Lagos',
        ]);

        $deskNotif = UserNotification::query()->where('user_id', $deskAdmin->id)->latest('id')->first();
        $this->assertNotNull($deskNotif, 'Desk Admin was not notified when State Officer submitted.');
        $this->assertFalse((bool) $deskNotif->is_read);
        $this->assertStringContainsString('Action Required:', $deskNotif->title);
        $this->assertSame(route('desk.admin.submissions.show', $stateApp), $deskNotif->action_url);

        // Verify Desk Admin's topbar bell shows unread count = 1 and notification title
        $this->actingAs($deskAdmin)->get(route('user.desk.home'))
            ->assertOk()
            ->assertSee('data-initial-unread="1"', false)
            ->assertSee($deskNotif->title);

        $this->actingAs($deskAdmin)->getJson('/user/notifications/count')
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        // 2. Desk Admin approves -> Zonal Admin (next officer) is notified
        SubmissionWorkflow::approve($stateApp, $deskAdmin, 'Looks good');
        $zonalNotif = UserNotification::query()->where('user_id', $zonalAdmin->id)->latest('id')->first();
        $this->assertNotNull($zonalNotif, 'Zonal Admin was not notified when Desk Admin approved.');
        $this->assertFalse((bool) $zonalNotif->is_read);
        $this->assertStringContainsString('Zonal Admin Review', $zonalNotif->description);

        // 3. Zonal Admin approves -> HQ Admin (next officer) is notified with direct review link
        SubmissionWorkflow::approve($stateApp, $zonalAdmin, 'Verified');
        $hqNotif = UserNotification::query()->where('user_id', $hqAdmin->id)->latest('id')->first();
        $this->assertNotNull($hqNotif, 'HQ Admin was not notified when Zonal Admin approved.');
        $this->assertFalse((bool) $hqNotif->is_read);
        $this->assertSame(route('desk.admin.submissions.show', $stateApp), $hqNotif->action_url);

        // 4. Directorate Officer submits -> Directorate Admin is notified
        $dirApp = SubmissionWorkflow::create($dirOfficer, [
            'period' => '2026-11',
            'directorate_slug' => 'ict',
            'reporting_officer' => $dirOfficer->name,
        ]);
        $dirNotif = UserNotification::query()->where('user_id', $dirAdmin->id)->latest('id')->first();
        $this->assertNotNull($dirNotif, 'Directorate Admin was not notified when Directorate Officer submitted.');
        $this->assertFalse((bool) $dirNotif->is_read);
        $this->assertStringContainsString('Directorate Admin Review', $dirNotif->description);

        // 5. Directorate Admin rejects -> Directorate Officer gets real-time rejected notification
        SubmissionWorkflow::reject($dirApp, $dirAdmin, 'Please verify server counts');
        $officerNotif = UserNotification::query()->where('user_id', $dirOfficer->id)->latest('id')->first();
        $this->assertNotNull($officerNotif, 'Directorate Officer was not notified on rejection.');
        $this->assertSame('rejected', $officerNotif->tag);
        $this->assertStringContainsString('Please verify server counts', $officerNotif->description);

        // 6. Newly provisioned Directorate Admin auto-syncs pending in-scope return notifications & keeps Directorate Admin sidebar
        $lateDirAdmin = $this->makeUser([
            'name' => 'Late ICT Admin',
            'service_number' => '91009',
            'email' => 'lateict91009@nis.gov.ng',
            'role' => 'admin',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'ict',
            'assigned_directorate_code' => 'ict',
            'access_level' => 2,
        ]);

        // Submit another ICT return, clear lateDirAdmin's notifications to simulate pre-existing submission
        $dirApp2 = SubmissionWorkflow::create($dirOfficer, [
            'period' => '2026-11',
            'directorate_slug' => 'ict',
            'reporting_officer' => $dirOfficer->name,
        ]);
        UserNotification::query()->where('user_id', $lateDirAdmin->id)->delete();

        $this->actingAs($lateDirAdmin)->get(route('user.desk.home'))
            ->assertOk()
            ->assertSee('Directorate Admin Menu')
            ->assertDontSee('Headquarters Menu')
            ->assertSee('data-initial-unread="1"', false)
            ->assertSee('Action Required: RET-'.str_pad((string) $dirApp2->id, 5, '0', STR_PAD_LEFT));
    }
}
