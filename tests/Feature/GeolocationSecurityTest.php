<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeolocationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeolocationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SettingService::set('abac_enabled', true, 'boolean');
        SettingService::set('abac_location_enforcement', true, 'boolean');
        SettingService::set('abac_hq_bypass', false, 'boolean');
        SettingService::set('abac_allowed_countries', 'Nigeria', 'string');
        SettingService::set('geolocation_provider', 'ipapi_co', 'string');
        SettingService::set('geolocation_unknown_policy', 'deny', 'string');
        Config::set('geolocation.retries', 0);
    }

    public function test_geolocation_uses_https_transport_only(): void
    {
        Http::fake([
            'https://ipapi.co/197.210.1.1/json/' => Http::response([
                'country_name' => 'Nigeria',
                'region' => 'Lagos',
                'region_code' => 'LA',
            ], 200),
        ]);

        $result = GeolocationService::resolve('197.210.1.1');

        $this->assertSame('Nigeria', $result['country']);
        $this->assertSame('LA', $result['state_code']);

        Http::assertSent(fn (HttpClientRequest $req): bool => str_starts_with($req->url(), 'https://'));
    }

    public function test_provider_failure_returns_unknown_and_denies_state_user_without_fc_fallback(): void
    {
        Http::fake([
            'https://ipapi.co/*' => Http::response('Service unavailable', 500),
        ]);

        $result = GeolocationService::resolve('197.210.2.2');
        $this->assertSame('', $result['state_code']);
        $this->assertSame('unknown', $result['provider']);

        // Even an FCT (FC) user must NOT be allowed via fallback when provider fails
        $fcUser = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'FC',
            'geo_state' => 'FC',
            'access_level' => 0,
        ]);

        $this->actingAs($fcUser)
            ->withServerVariables(['REMOTE_ADDR' => '197.210.2.2'])
            ->get('/user/dashboard')
            ->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $fcUser->id,
            'action' => 'auth.blocked',
            'status' => 'blocked',
        ]);
    }

    public function test_malformed_provider_response_and_unknown_region_are_handled_safely(): void
    {
        Http::fake([
            'https://ipapi.co/197.210.3.3/json/' => Http::response('not-json', 200),
            'https://ipapi.co/197.210.4.4/json/' => Http::response([
                'country_name' => 'Nigeria',
                'region' => 'Atlantis',
                'region_code' => 'XX',
            ], 200),
        ]);

        $malformed = GeolocationService::resolve('197.210.3.3');
        $this->assertSame('', $malformed['state_code']);
        $this->assertSame('unknown', $malformed['provider']);

        $unknownRegion = GeolocationService::resolve('197.210.4.4');
        $this->assertSame('Nigeria', $unknownRegion['country']);
        $this->assertSame('', $unknownRegion['state_code']);
    }

    public function test_untrusted_x_forwarded_for_header_is_ignored_while_trusted_proxy_is_honored(): void
    {
        // Untrusted remote IP sending spoofed X-Forwarded-For must be ignored
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        $untrusted = Request::create('/user/dashboard', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_X_FORWARDED_FOR' => '197.210.5.5',
        ]);
        $this->assertSame('203.0.113.50', $untrusted->ip());

        // Explicitly trusted proxy IP honors X-Forwarded-For
        Request::setTrustedProxies(['10.0.0.10'], Request::HEADER_X_FORWARDED_FOR);
        $trusted = Request::create('/user/dashboard', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '197.210.5.5',
        ]);
        $this->assertSame('197.210.5.5', $trusted->ip());

        // Reset trusted proxies for subsequent tests
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }
}
