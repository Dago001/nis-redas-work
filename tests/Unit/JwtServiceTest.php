<?php

namespace Tests\Unit;

use App\Exceptions\JwtConfigurationException;
use App\Services\JwtService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use UnexpectedValueException;

class JwtServiceTest extends TestCase
{
    public function test_missing_secret_throws_configuration_exception(): void
    {
        Config::set('jwt.secret', null);

        $this->expectException(JwtConfigurationException::class);
        JwtService::assertConfigured();
    }

    public function test_short_secret_throws_configuration_exception(): void
    {
        Config::set('jwt.secret', 'too-short-secret');

        $this->expectException(JwtConfigurationException::class);
        JwtService::fromConfig();
    }

    public function test_placeholder_secret_throws_configuration_exception(): void
    {
        Config::set('jwt.secret', 'dev-fallback-jwt-secret-dev-fallback');

        $this->expectException(JwtConfigurationException::class);
        JwtService::fromConfig();
    }

    public function test_key_rotation_verifies_tokens_signed_with_previous_secret(): void
    {
        $oldSecret = 'base64:'.base64_encode(random_bytes(48));
        $newSecret = 'base64:'.base64_encode(random_bytes(48));

        Config::set('jwt.secret', $oldSecret);
        Config::set('jwt.previous_secrets', []);
        $oldToken = JwtService::fromConfig()->issueAccessToken(42, 'officer', 'jti-1', 'fam-1');

        // Rotate to new secret, keeping old in previous_secrets
        Config::set('jwt.secret', $newSecret);
        Config::set('jwt.previous_secrets', [$oldSecret]);
        $decoded = JwtService::fromConfig()->decodeAccess($oldToken);
        $this->assertSame('42', $decoded->sub);
        $this->assertSame('fam-1', $decoded->fam);

        // Drop old secret -> verification fails
        Config::set('jwt.previous_secrets', []);
        $this->expectException(\Throwable::class);
        JwtService::fromConfig()->decodeAccess($oldToken);
    }

    public function test_token_type_substitution_is_rejected(): void
    {
        $service = JwtService::fromConfig();
        $refresh = $service->issueRefreshToken(7, 'officer', 'jti-r', 'fam-1');
        $access = $service->issueAccessToken(7, 'officer', 'jti-a', 'fam-1');

        try {
            $service->decodeAccess($refresh);
            $this->fail('Expected refresh token to be rejected by decodeAccess');
        } catch (UnexpectedValueException) {
            $this->assertTrue(true);
        }

        $this->expectException(UnexpectedValueException::class);
        $service->decodeRefresh($access);
    }

    public function test_wrong_issuer_or_audience_is_rejected(): void
    {
        $rawKey = random_bytes(48);
        $issuerA = new JwtService($rawKey, issuer: 'issuer-a', audience: 'aud-a');
        $issuerB = new JwtService($rawKey, issuer: 'issuer-b', audience: 'aud-a');
        $audB = new JwtService($rawKey, issuer: 'issuer-a', audience: 'aud-b');

        $token = $issuerA->issueAccessToken(1, 'officer', 'jti-1', 'fam-1');

        try {
            $issuerB->decodeAccess($token);
            $this->fail('Expected wrong issuer to be rejected');
        } catch (UnexpectedValueException) {
            $this->assertTrue(true);
        }

        $this->expectException(UnexpectedValueException::class);
        $audB->decodeAccess($token);
    }
}
