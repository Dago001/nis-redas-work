<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_applied_and_no_external_font_cdn_is_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('X-Powered-By'));
        $response->assertDontSee('fonts.bunny.net');
    }

    public function test_waf_blocks_sql_injection_xss_traversal_and_null_bytes_and_audits(): void
    {
        $attacks = [
            '/login?email=admin%27%20UNION%20SELECT%201,2,3--',
            '/login?id=1%27%20OR%201=1--',
            '/login?q=SLEEP(5)',
            '/login?file=../../etc/passwd',
            '/login?name=%3Cscript%3Ealert(1)%3C/script%3E',
            '/login?probe=test%00.pdf',
        ];

        foreach ($attacks as $url) {
            $this->get($url)->assertStatus(403);
        }

        $this->assertSame(
            count($attacks),
            AuditLog::where('action', 'security.waf_blocked')->count()
        );
    }

    public function test_waf_blocks_sqli_and_script_tags_in_post_body_but_allows_legitimate_text(): void
    {
        $this->postJson('/api/login', [
            'email' => "admin' UNION SELECT password FROM users--",
            'password' => 'Secret123!',
        ])->assertStatus(403);

        // Legitimate apostrophe/hyphen text must not be blocked by WAF
        $legit = $this->postJson('/api/login', [
            'email' => "o'connor-test@nis.gov.ng",
            'password' => 'WrongPassword123!',
        ]);
        $this->assertNotSame(403, $legit->getStatusCode());
    }

    public function test_gzip_compression_shrinks_html_payloads_for_low_bandwidth_clients(): void
    {
        $uncompressed = $this->get('/login');
        $uncompressed->assertStatus(200);
        $rawLength = strlen((string) $uncompressed->getContent());
        $this->assertGreaterThan(1024, $rawLength);
        $this->assertFalse($uncompressed->headers->has('Content-Encoding'));

        $compressed = $this->withHeader('Accept-Encoding', 'gzip, deflate')->get('/login');
        $compressed->assertStatus(200);
        $compressed->assertHeader('Content-Encoding', 'gzip');
        $this->assertStringContainsString('Accept-Encoding', (string) $compressed->headers->get('Vary'));

        $gzipBytes = (string) $compressed->getContent();
        $this->assertLessThan($rawLength * 0.5, strlen($gzipBytes), 'Gzip should reduce HTML payload by >50%');
        $decoded = gzdecode($gzipBytes);
        $this->assertIsString($decoded);
        $this->assertStringContainsString('NIS', $decoded);
    }

    public function test_authenticated_dashboard_renders_mobile_app_bottom_navigation(): void
    {
        $user = User::factory()->create([
            'role' => 'directorate_admin',
            'user_category' => 'directorate_admin',
            'assigned_directorate_code' => 'prs',
            'email_verified_at' => now(),
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->get(route('prs.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('redas-mobile-bottom-nav');
        $response->assertSee('mobileMenuDrawerBtn');
        $response->assertDontSee('fonts.bunny.net');
    }
}
