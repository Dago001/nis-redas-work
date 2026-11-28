<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemSettingsAndBackupTest extends TestCase
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
            'name' => 'Test User',
            'email_verified_at' => now(),
            'password' => Hash::make('Secure@12'),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'geolocation_enabled' => false,
        ], $attrs));
    }

    public function test_settings_maintenance_mode_and_full_database_backup_workflow(): void
    {
        $superAdmin = $this->makeUser([
            'name' => 'Super Admin',
            'service_number' => '96001',
            'email' => 'super96001@nis.gov.ng',
            'role' => 'admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 6,
        ]);

        $stateUser = $this->makeUser([
            'name' => 'Lagos Officer',
            'service_number' => '96002',
            'email' => 'state96002@nis.gov.ng',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
        ]);

        // 1. Settings page renders all Platform Control sections
        $this->actingAs($superAdmin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Maintenance Mode')
            ->assertSee('Database Backup')
            ->assertSee('Backup Entire Database Now');

        // 2. Enable Maintenance Mode -> regular officer gets 503, Super Admin stays 200
        $payload = [
            'maintenance_mode' => '1',
            'maintenance_message' => 'Scheduled NIS database upgrade in progress.',
            'maintenance_eta' => 'Today at 18:00 WAT',
            'system_banner_enabled' => '1',
            'system_banner_message' => 'Monthly returns close on the 25th.',
            'submissions_open' => '1',
            'submission_deadline_day' => '25',
            'allow_return_resubmission' => '1',
            'mfa_required' => '1',
            'session_timeout_minutes' => '120',
            'max_login_attempts' => '5',
            'force_password_change_on_create' => '1',
            'abac_enabled' => '0',
            'abac_location_enforcement' => '0',
            'abac_hq_bypass' => '1',
            'abac_allowed_countries' => 'Nigeria',
            'geolocation_provider' => 'ipapi_co',
            'default_geofence_radius_km' => '25',
            'geolocation_api_token' => '',
        ];

        $this->actingAs($superAdmin)->put(route('admin.settings.update'), $payload)
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasNoErrors();

        $this->actingAs($stateUser)->get(route('user.dashboard'))
            ->assertStatus(503)
            ->assertSee('Scheduled NIS database upgrade in progress.')
            ->assertSee('Today at 18:00 WAT');

        $this->actingAs($superAdmin)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('superadmin.dashboard'))->assertOk();

        // Turn Maintenance Mode back off -> state user regains access
        $payload['maintenance_mode'] = '0';
        $this->actingAs($superAdmin)->put(route('admin.settings.update'), $payload)
            ->assertRedirect(route('admin.settings.index'));
        $this->actingAs($stateUser)->get(route('user.dashboard'))->assertOk();

        // 3. Create, verify, download, and delete a full database backup
        $beforeCount = count(DatabaseBackupService::listBackups());
        $this->actingAs($superAdmin)->post(route('admin.settings.backup.create'))
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status');

        $backups = DatabaseBackupService::listBackups();
        $this->assertGreaterThan($beforeCount, count($backups));
        $latest = $backups[0];
        $this->assertGreaterThan(0, $latest['size_bytes']);

        $this->actingAs($superAdmin)->get(route('admin.settings.backup.download', ['filename' => $latest['filename']]))
            ->assertOk();

        $this->actingAs($superAdmin)->delete(route('admin.settings.backup.delete', ['filename' => $latest['filename']]))
            ->assertRedirect(route('admin.settings.index'));

        $this->assertNull(DatabaseBackupService::safePath($latest['filename']));

        // 4. Clear cache works
        $this->actingAs($superAdmin)->post(route('admin.settings.clear-cache'))
            ->assertRedirect(route('admin.settings.index'));

        // 5. Regular state user is forbidden from settings & backups
        $this->actingAs($stateUser)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($stateUser)->post(route('admin.settings.backup.create'))->assertForbidden();
    }
}
