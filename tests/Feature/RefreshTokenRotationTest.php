<?php

namespace Tests\Feature;

use App\Models\AuthChallenge;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RefreshTokenRotationTest extends TestCase
{
    use RefreshDatabase;

    private function issueSessionFor(User $user): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $otp = '445566';

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
        $challenge->otp_hash = AuthChallenge::hashOtp($challenge->id, $otp);
        $challenge->save();

        $res = $this->postJson('/api/otp/verify', [
            'challenge_token' => $rawToken,
            'otp' => $otp,
        ]);
        $res->assertStatus(200);

        return [
            'access_token' => $res->json('access_token'),
            'refresh_token' => $res->json('refresh_token'),
        ];
    }

    public function test_refresh_rotates_token_and_reuse_revokes_entire_family(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('SecretPass123!'),
            'email_verified_at' => now(),
            'is_enabled' => true,
        ]);

        $session1 = $this->issueSessionFor($user);

        // Access token works on protected endpoint
        $this->withHeader('Authorization', 'Bearer '.$session1['access_token'])
            ->getJson('/api/user')
            ->assertOk();

        // Refresh rotates to session2
        $rotated = $this->postJson('/api/refresh', [
            'refresh_token' => $session1['refresh_token'],
        ]);
        $rotated->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        $session2Refresh = $rotated->json('refresh_token');
        $session2Access = $rotated->json('access_token');

        // Reusing the already-rotated session1 refresh token triggers family revocation
        $this->postJson('/api/refresh', [
            'refresh_token' => $session1['refresh_token'],
        ])->assertStatus(401)->assertJsonPath('message', 'TOKEN_REVOKED');

        // Both the new refresh token AND the new access token in that family are now revoked
        $this->postJson('/api/refresh', [
            'refresh_token' => $session2Refresh,
        ])->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer '.$session2Access)
            ->getJson('/api/user')
            ->assertStatus(401);

        $this->assertTrue(
            RefreshToken::query()->where('user_id', $user->id)->whereNotNull('reuse_detected_at')->exists()
        );
    }

    public function test_refresh_token_cannot_be_used_as_bearer_access_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_enabled' => true,
        ]);

        $session = $this->issueSessionFor($user);

        $this->withHeader('Authorization', 'Bearer '.$session['refresh_token'])
            ->getJson('/api/user')
            ->assertStatus(401);
    }
}
