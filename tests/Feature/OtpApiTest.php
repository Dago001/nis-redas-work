<?php

namespace Tests\Feature;

use App\Models\AuthChallenge;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role = 'officer'): User
    {
        return User::factory()->create([
            'service_number' => '1234',
            'role' => $role,
            'email' => 'user@example.com',
            'password' => Hash::make('SecretPass123!'),
            'email_verified_at' => now(),
            'is_enabled' => true,
        ]);
    }

    private function seedOtpTemplates(): void
    {
        EmailTemplate::query()->create([
            'key' => 'otp_login',
            'type' => 'otp',
            'subject' => 'Your login code is {{ otp }}',
            'body' => 'Hi {{ name }}, your otp is {{ otp }}. Expires in {{ expires_in_minutes }} minutes.',
            'is_active' => true,
        ]);
    }

    public function test_login_with_valid_password_creates_challenge_and_sends_otp(): void
    {
        Config::set('services.resend.key', 're_test_key');
        Mail::fake();

        $user = $this->createUser();
        $this->seedOtpTemplates();

        $res = $this->postJson('/api/login', [
            'login' => $user->email,
            'password' => 'SecretPass123!',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('message', 'OTP_REQUIRED');
        $res->assertJsonStructure(['challenge_token', 'expires_at', 'otp_digits']);

        $this->assertDatabaseHas('auth_challenges', [
            'user_id' => $user->id,
            'purpose' => 'login',
            'consumed_at' => null,
        ]);
    }

    public function test_otp_verify_consumes_matching_challenge_and_issues_tokens(): void
    {
        $user = $this->createUser();
        $this->seedOtpTemplates();

        $rawToken = bin2hex(random_bytes(32));
        $knownOtp = '654321';

        $challenge = AuthChallenge::query()->create([
            'token_hash' => AuthChallenge::hashToken($rawToken),
            'user_id' => $user->id,
            'purpose' => 'login',
            'otp_hash' => str_repeat('0', 64),
            'attempts' => 0,
            'max_attempts' => 5,
            'sends' => 1,
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => null,
        ]);
        $challenge->otp_hash = AuthChallenge::hashOtp($challenge->id, $knownOtp);
        $challenge->save();

        $res = $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => $knownOtp,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('message', 'OTP verified successfully.');
        $res->assertJsonPath('user_id', $user->id);
        $res->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);

        $challenge->refresh();
        $this->assertNotNull($challenge->consumed_at);
    }
}
