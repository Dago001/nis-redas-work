<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'Password123!';

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Login Test User',
            'service_number' => '7777',
            'email' => 'login@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make($this->password),
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'access_level' => 0,
        ], $overrides));
    }

    private function completeMfaSetup(User $user): void
    {
        $mfaService = app(MfaService::class);

        $this->get('/mfa/setup')->assertOk();
        $user->refresh();
        $this->assertNotNull($user->mfa_secret);

        $code = $mfaService->currentCode($user->mfa_secret);
        $this->post('/mfa/setup', ['code' => $code])->assertOk();
        $this->post('/mfa/complete')->assertRedirect();
    }

    private function loginPayload(User $user, ?string $role = null): array
    {
        RateLimiter::clear(strtolower($user->service_number).'|127.0.0.1');

        return [
            'login' => $user->service_number,
            'password' => $this->password,
            'role' => $role ?? $user->role,
        ];
    }

    public function test_users_can_login_with_service_number_and_role(): void
    {
        $user = $this->createUser();

        $response = $this->post('/login', $this->loginPayload($user));
        $response->assertRedirect('/mfa/setup');

        $this->completeMfaSetup($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_can_login_with_role_alias(): void
    {
        $user = $this->createUser([
            'name' => 'Super Admin Test',
            'service_number' => '1234',
            'email' => 'superadmin@example.com',
            'role' => 'admin',
            'user_category' => 'super_admin',
            'primary_location_type' => 'headquarters',
            'access_level' => 6,
        ]);

        $response = $this->post('/login', [
            'login' => $user->service_number,
            'password' => $this->password,
            'role' => 'super_admin',
        ]);
        $response->assertRedirect('/mfa/setup');

        $this->completeMfaSetup($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_when_password_is_incorrect(): void
    {
        $user = $this->createUser([
            'name' => 'Bad Password Test',
            'service_number' => '8888',
            'email' => 'wrongrole@example.com',
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => $user->service_number,
            'password' => 'WrongPassword999!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_state_user_can_access_user_dashboard(): void
    {
        $user = $this->createUser([
            'name' => 'State Dashboard User',
            'service_number' => '9999',
            'email' => 'state-dashboard@example.com',
        ]);

        $this->post('/login', $this->loginPayload($user))->assertRedirect('/mfa/setup');
        $this->completeMfaSetup($user);

        $response = $this->actingAs($user)->get('/user/dashboard');
        $response->assertOk();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = $this->createUser([
            'name' => 'Admin Dashboard User',
            'service_number' => '9999',
            'email' => 'admin-dashboard@example.com',
            'role' => 'admin',
            'user_category' => 'admin',
            'primary_location_type' => 'headquarters',
            'access_level' => 5,
        ]);

        $this->post('/login', [
            'login' => $user->service_number,
            'password' => $this->password,
            'role' => 'admin',
        ])->assertRedirect('/mfa/setup');
        $this->completeMfaSetup($user);

        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertOk();
    }

    public function test_login_rejects_email_and_requires_service_number(): void
    {
        $user = $this->createUser([
            'service_number' => '4321',
            'email' => 'sn-only@example.com',
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => $user->email,
            'password' => $this->password,
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_password_policy_requires_min_6_chars_upper_lower_number_and_special_char(): void
    {
        $user = $this->createUser([
            'service_number' => '1111',
            'email' => 'pw-policy@example.com',
        ]);

        // Weak passwords (too short, no uppercase, no number, no special char) must fail
        foreach (['Ab1!c', 'abcdef1!', 'ABCDEF1!', 'Abcdef!', 'Abcdef1'] as $weak) {
            $this->actingAs($user)->patch(route('user.profile.update'), [
                'current_password' => $this->password,
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        // 6+ chars with Upper, Lower, Number, Special char must succeed
        $this->actingAs($user)->patch(route('user.profile.update'), [
            'current_password' => $this->password,
            'password' => 'Ab1!cd',
            'password_confirmation' => 'Ab1!cd',
        ])->assertSessionHasNoErrors()->assertRedirect(route('user.profile'));
    }
}
