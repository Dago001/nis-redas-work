<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoAndPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::set('abac_enabled', false, 'boolean');
    }

    public function test_user_can_upload_change_and_remove_profile_photo_and_see_it_in_header(): void
    {
        Storage::fake('local');

        $user = User::create([
            'name' => 'Super Admin',
            'service_number' => '97001',
            'email' => 'super97001@nis.gov.ng',
            'phone' => '08091234567',
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

        // Upload valid PNG profile photo
        $file = UploadedFile::fake()->image('officer.png', 200, 200);
        $this->actingAs($user)->post(route('user.profile.photo.update'), [
            'photo' => $file,
        ])->assertRedirect(route('user.profile'))
          ->assertSessionHas('status');

        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        Storage::disk('local')->assertExists($user->profile_photo_path);

        // Header on dashboard and profile renders the uploaded photo URL
        $photoPath = parse_url($user->profilePhotoUrl(), PHP_URL_PATH);
        $this->actingAs($user)->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee($photoPath, false);

        // Serving the photo endpoint returns HTTP 200
        $this->actingAs($user)->get(route('user.profile.photo.show', $user))->assertOk();

        // Removing the photo clears it and deletes the file
        $oldPath = $user->profile_photo_path;
        $this->actingAs($user)->delete(route('user.profile.photo.destroy'))
            ->assertRedirect(route('user.profile'));

        $user->refresh();
        $this->assertNull($user->profile_photo_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_phone_number_is_mandatory_when_creating_new_user(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'service_number' => '97002',
            'email' => 'super97002@nis.gov.ng',
            'phone' => '08090000001',
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

        // Missing phone fails validation
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Bello',
            'first_name' => 'Musa',
            'service_number' => '97003',
            'rank' => 'CSI',
            'email' => 'musa97003@nis.gov.ng',
            'user_category' => 'state_user',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
            'is_enabled' => '1',
        ])->assertSessionHasErrors('phone');

        // Non-11-digit or letters in phone fail validation
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Bello',
            'first_name' => 'Musa',
            'service_number' => '97003',
            'rank' => 'CSI',
            'email' => 'musa97003@nis.gov.ng',
            'phone' => '0803555019', // only 10 digits
            'user_category' => 'state_user',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
            'is_enabled' => '1',
        ])->assertSessionHasErrors('phone');

        // Numbers in name fields fail validation
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Bello123',
            'first_name' => 'Musa9',
            'service_number' => '97003',
            'rank' => 'CSI',
            'email' => 'musa97003@nis.gov.ng',
            'phone' => '08035550199',
            'user_category' => 'state_user',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
            'is_enabled' => '1',
        ])->assertSessionHasErrors(['surname', 'first_name']);

        // Providing exact 11-digit phone and letters-only names succeeds and persists
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'surname' => 'Bello',
            'first_name' => 'Musa',
            'service_number' => '97003',
            'rank' => 'CSI',
            'email' => 'musa97003@nis.gov.ng',
            'phone' => '08035550199',
            'user_category' => 'state_user',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'password' => 'Secure@12',
            'password_confirmation' => 'Secure@12',
            'is_enabled' => '1',
        ])->assertRedirect(route('admin.users'))
          ->assertSessionHasNoErrors();

        $created = User::where('service_number', '97003')->firstOrFail();
        $this->assertSame('08035550199', $created->phone);
    }
}
