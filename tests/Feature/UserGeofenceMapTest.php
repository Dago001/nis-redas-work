<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserGeofenceMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::set('abac_enabled', false, 'boolean');
    }

    public function test_super_admin_can_create_and_edit_user_with_live_geofence_map_coordinates(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'service_number' => '95001',
            'email' => 'super95001@nis.gov.ng',
            'role' => 'admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 6,
            'password' => Hash::make('Secure@12'),
            'email_verified_at' => now(),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'geolocation_enabled' => false,
        ]);

        // Create page renders Live Geofence Map & controls
        $this->actingAs($superAdmin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('id="geofenceLiveMap"', false)
            ->assertSee('name="geofence_lat"', false)
            ->assertSee('name="geofence_lng"', false)
            ->assertSee('name="geofence_radius_km"', false);

        // Create officer with pinned Lagos coordinates & 15 km geofence radius
        $createRes = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Okafor',
            'first_name' => 'Chinedu',
            'middle_name' => 'K.',
            'service_number' => '95002',
            'rank' => 'CSI',
            'email' => 'chinedu95002@nis.gov.ng',
            'user_category' => 'state_user',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'geolocation_enabled' => '1',
            'geofence_lat' => '6.524400',
            'geofence_lng' => '3.379200',
            'geofence_radius_km' => '15',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
            'is_enabled' => '1',
        ]);
        $createRes->assertRedirect(route('admin.users'));

        $created = User::where('service_number', '95002')->firstOrFail();
        $this->assertTrue((bool) $created->geolocation_enabled);
        $this->assertEqualsWithDelta(6.5244, (float) $created->geofence_lat, 0.0001);
        $this->assertEqualsWithDelta(3.3792, (float) $created->geofence_lng, 0.0001);
        $this->assertSame(15, (int) $created->geofence_radius_km);

        // Edit page loads saved geofence coordinates & radius back onto the live map
        $this->actingAs($superAdmin)->get(route('admin.users.edit', $created))
            ->assertOk()
            ->assertSee('id="geofenceLiveMap"', false)
            ->assertSee('6.5244')
            ->assertSee('3.3792')
            ->assertSee('15 km');

        // Updating coordinates & radius persists cleanly
        $this->actingAs($superAdmin)->patch(route('admin.users.update', $created), [
            'surname' => 'Okafor',
            'first_name' => 'Chinedu',
            'middle_name' => 'K.',
            'service_number' => '95002',
            'rank' => 'CSI',
            'email' => 'chinedu95002@nis.gov.ng',
            'user_category' => 'state_user',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'geolocation_enabled' => '1',
            'geofence_lat' => '6.601800',
            'geofence_lng' => '3.351500',
            'geofence_radius_km' => '10',
            'is_enabled' => '1',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $created->refresh();
        $this->assertEqualsWithDelta(6.6018, (float) $created->geofence_lat, 0.0001);
        $this->assertEqualsWithDelta(3.3515, (float) $created->geofence_lng, 0.0001);
        $this->assertSame(10, (int) $created->geofence_radius_km);
    }
}
