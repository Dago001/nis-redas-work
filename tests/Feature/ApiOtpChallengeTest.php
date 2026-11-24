<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AuthChallenge;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiOtpChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.resend.key', 're_test_key');
        Mail::fake();

        EmailTemplate::query()->create([
            'key' => 'otp_login',
            'type' => 'otp',
            'subject' => 'Code {{ otp }}',
            'body' => 'Hi {{ name }}, code is {{ otp }}.',
            'is_active' => true,
        ]);
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'service_number' => '4321',
            'email' => 'officer.otp@nis.gov.ng',
            'password' => Hash::make('StrongPass123!'),
            'role' => 'officer',
            'email_verified_at' => now(),
            'is_enabled' => true,
            'must_change_password' => false,
        ], $overrides));
    }

    private function createKnownChallenge(User $user, string $otp = '123456', int $ttlSeconds = 300, int $maxAttempts = 5): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $challenge = AuthChallenge::query()->create([
            'token_hash' => AuthChallenge::hashToken($rawToken),
            'user_id' => $user->id,
            'purpose' => 'login',
            'otp_hash' => str_repeat('0', 64),
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'sends' => 1,
            'last_sent_at' => now()->subSeconds(60),
            'expires_at' => now()->addSeconds($ttlSeconds),
            'consumed_at' => null,
        ]);
        $challenge->otp_hash = AuthChallenge::hashOtp($challenge->id, $otp);
        $challenge->save();

        return [$rawToken, $challenge];
    }

    public function test_otp_endpoints_cannot_bypass_password_authentication(): void
    {
        $user = $this->makeUser();

        // Attempting legacy login-only OTP request fails validation (no challenge_token)
        $this->postJson('/api/otp/request', [
            'login' => $user->email,
            'purpose' => 'login',
        ])->assertStatus(422);

        // Attempting legacy login-only OTP verify fails validation
        $this->postJson('/api/otp/verify', [
            'login' => $user->email,
            'purpose' => 'login',
            'otp' => '123456',
        ])->assertStatus(422);

        // Random challenge token cannot request or verify
        $fakeToken = bin2hex(random_bytes(32));
        $this->postJson('/api/otp/request', ['challenge_token' => $fakeToken])->assertStatus(401);
        $this->postJson('/api/otp/verify', ['challenge_token' => $fakeToken, 'otp' => '123456'])->assertStatus(401);

        $this->assertDatabaseCount('auth_challenges', 0);
        $this->assertDatabaseCount('refresh_tokens', 0);
    }

    public function test_wrong_password_and_unknown_user_return_identical_generic_error(): void
    {
        $user = $this->makeUser();

        $wrongPassword = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $unknownUser = $this->postJson('/api/login', [
            'login' => 'does.not.exist@nis.gov.ng',
            'password' => 'WrongPassword!',
        ]);

        $wrongPassword->assertStatus(401)->assertExactJson(['message' => 'INVALID_CREDENTIALS']);
        $unknownUser->assertStatus(401)->assertExactJson(['message' => 'INVALID_CREDENTIALS']);
    }

    public function test_otp_expires_and_cannot_be_verified_after_ttl(): void
    {
        $user = $this->makeUser();
        [$rawToken] = $this->createKnownChallenge($user, '246810', ttlSeconds: 60);

        $this->travel(61)->seconds();

        $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '246810',
        ])->assertStatus(401);
    }

    public function test_otp_is_single_use_and_prevents_replay(): void
    {
        $user = $this->makeUser();
        [$rawToken] = $this->createKnownChallenge($user, '987654');

        $first = $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '987654',
        ]);
        $first->assertStatus(200)->assertJsonStructure(['access_token', 'refresh_token']);

        // Replay of the exact same OTP must fail
        $replay = $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '987654',
        ]);
        $replay->assertStatus(401);
    }

    public function test_attempt_limit_blocks_even_the_correct_otp(): void
    {
        $user = $this->makeUser();
        [$rawToken] = $this->createKnownChallenge($user, '112233', maxAttempts: 3);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/otp/verify', [
                'challenge_token' => $rawToken,
                'otp' => '000000',
            ])->assertStatus(401);
        }

        // 4th attempt with the CORRECT code must still be rejected
        $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '112233',
        ])->assertStatus(429)->assertJsonPath('message', 'OTP_ATTEMPTS_EXCEEDED');
    }

    public function test_resend_respects_cooldown_and_max_resend_limit(): void
    {
        Config::set('otp.max_resends', 1);
        Config::set('otp.resend_interval', 30);

        $user = $this->makeUser();
        [$rawToken, $challenge] = $this->createKnownChallenge($user, '111111');
        $challenge->update(['last_sent_at' => now()]);

        // Immediate resend blocked by cooldown
        $this->postJson('/api/otp/request', ['challenge_token' => $rawToken])
            ->assertStatus(429);

        // After cooldown, 1st resend succeeds
        $this->travel(31)->seconds();
        $this->postJson('/api/otp/request', ['challenge_token' => $rawToken])
            ->assertStatus(200);

        // 2nd resend exceeds max_resends=1
        $this->travel(31)->seconds();
        $this->postJson('/api/otp/request', ['challenge_token' => $rawToken])
            ->assertStatus(429);
    }

    public function test_disabled_account_rejected_at_login_and_between_challenge_and_verify(): void
    {
        $disabled = $this->makeUser(['is_enabled' => false, 'email' => 'disabled@nis.gov.ng', 'service_number' => '4322']);

        $this->postJson('/api/login', [
            'login' => $disabled->email,
            'password' => 'StrongPass123!',
        ])->assertStatus(403)->assertJsonPath('message', 'ACCOUNT_DISABLED');

        // Enabled at login, disabled before OTP verify
        $user = $this->makeUser(['email' => 'later.disabled@nis.gov.ng', 'service_number' => '4323']);
        [$rawToken] = $this->createKnownChallenge($user, '555666');
        $user->update(['is_enabled' => false]);

        $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '555666',
        ])->assertStatus(403)->assertJsonPath('message', 'ACCOUNT_DISABLED');
    }

    public function test_unverified_email_and_must_change_password_are_enforced(): void
    {
        $unverified = $this->makeUser(['email_verified_at' => null, 'email' => 'unverified@nis.gov.ng', 'service_number' => '4324']);
        $this->postJson('/api/login', [
            'login' => $unverified->email,
            'password' => 'StrongPass123!',
        ])->assertStatus(403)->assertJsonPath('message', 'EMAIL_NOT_VERIFIED');

        $mustChange = $this->makeUser(['must_change_password' => true, 'email' => 'mustchange@nis.gov.ng', 'service_number' => '4325']);
        [$rawToken] = $this->createKnownChallenge($mustChange, '777888');
        $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '777888',
        ])->assertStatus(403)->assertJsonPath('message', 'PASSWORD_CHANGE_REQUIRED');
    }

    public function test_audit_logs_record_auth_events_without_secrets(): void
    {
        $user = $this->makeUser();
        [$rawToken] = $this->createKnownChallenge($user, '334455');

        $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'WrongSecret999!',
        ])->assertStatus(401);

        $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => '334455',
        ])->assertStatus(200);

        $logs = AuditLog::query()->get();
        $this->assertTrue($logs->contains('action', 'api.login.password_failed'));
        $this->assertTrue($logs->contains('action', 'api.login.success'));

        $serialized = json_encode($logs->toArray());
        $this->assertStringNotContainsString('WrongSecret999!', $serialized);
        $this->assertStringNotContainsString('334455', $serialized);
        $this->assertStringNotContainsString($rawToken, $serialized);
    }
}
