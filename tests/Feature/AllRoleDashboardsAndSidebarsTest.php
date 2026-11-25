<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AllRoleDashboardsAndSidebarsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::set('abac_enabled', false, 'boolean');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function roleProfiles(): array
    {
        return [
            'state_user' => [
                'service_number' => '70001',
                'email' => 'state.officer@nis.gov.ng',
                'role' => 'officer',
                'user_category' => 'state_user',
                'primary_location_type' => 'state',
                'primary_location_code' => 'LASC',
                'assigned_state_code' => 'LASC',
                'assigned_zonal_command_code' => 'ZONE-A',
                'access_level' => 0,
            ],
            'desk_admin' => [
                'service_number' => '70002',
                'email' => 'desk.admin@nis.gov.ng',
                'role' => 'state',
                'user_category' => 'desk_admin',
                'primary_location_type' => 'state',
                'primary_location_code' => 'LASC',
                'assigned_state_code' => 'LASC',
                'assigned_zonal_command_code' => 'ZONE-A',
                'access_level' => 1,
            ],
            'zonal_commander' => [
                'service_number' => '70003',
                'email' => 'zonal.cmd@nis.gov.ng',
                'role' => 'zonal',
                'user_category' => 'zonal_commander',
                'primary_location_type' => 'zonal',
                'primary_location_code' => 'ZONE-A',
                'assigned_zonal_command_code' => 'ZONE-A',
                'access_level' => 3,
            ],
            'directorate_user' => [
                'service_number' => '70004',
                'email' => 'dir.officer@nis.gov.ng',
                'role' => 'directorate',
                'user_category' => 'directorate_user',
                'primary_location_type' => 'directorate',
                'primary_location_code' => 'hrm',
                'assigned_directorate_code' => 'hrm',
                'access_level' => 0,
            ],
            'directorate_admin' => [
                'service_number' => '70005',
                'email' => 'dir.admin@nis.gov.ng',
                'role' => 'directorate',
                'user_category' => 'directorate_admin',
                'primary_location_type' => 'directorate',
                'primary_location_code' => 'prs',
                'assigned_directorate_code' => 'prs',
                'access_level' => 2,
            ],
            'cgis_unit_user' => [
                'service_number' => '70006',
                'email' => 'cgis.officer@nis.gov.ng',
                'role' => 'unit_officer',
                'user_category' => 'cgis_unit_user',
                'primary_location_type' => 'unit',
                'primary_location_code' => 'servicom',
                'assigned_cgis_unit_code' => 'servicom',
                'access_level' => 0,
            ],
            'cgis_desk_admin' => [
                'service_number' => '70007',
                'email' => 'cgis.desk@nis.gov.ng',
                'role' => 'unit_admin',
                'user_category' => 'cgis_desk_admin',
                'primary_location_type' => 'unit',
                'primary_location_code' => 'servicom',
                'assigned_cgis_unit_code' => 'servicom',
                'access_level' => 2,
            ],
            'hq_admin' => [
                'service_number' => '70008',
                'email' => 'hq.admin@nis.gov.ng',
                'role' => 'admin',
                'user_category' => 'hq_admin',
                'primary_location_type' => 'headquarters',
                'primary_location_code' => 'HQ',
                'access_level' => 5,
            ],
            'super_admin' => [
                'service_number' => '70009',
                'email' => 'super.admin@nis.gov.ng',
                'role' => 'admin',
                'user_category' => 'super_admin',
                'primary_location_type' => 'headquarters',
                'primary_location_code' => 'HQ',
                'access_level' => 6,
            ],
        ];
    }

    /**
     * Extract all href links inside <aside class="redas-sidebar">...</aside>.
     *
     * @return list<string>
     */
    private function extractSidebarLinks(string $html): array
    {
        if (! preg_match('/<aside[^>]*id="redasSidebar"[^>]*>(.*?)<\/aside>/si', $html, $aside)) {
            return [];
        }

        preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $aside[1], $matches);

        return array_values(array_unique(array_filter(
            $matches[1] ?? [],
            static fn (string $href): bool => $href !== '' && $href !== '#' && ! str_starts_with($href, 'javascript:')
        )));
    }

    public function test_every_role_dashboard_profile_and_every_sidebar_link_loads_without_403_or_404(): void
    {
        foreach ($this->roleProfiles() as $label => $attrs) {
            $user = User::create(array_merge([
                'name' => ucwords(str_replace('_', ' ', $label)),
                'email_verified_at' => now(),
                'password' => Hash::make('Secure@12'),
                'is_enabled' => true,
                'must_change_password' => false,
                'mfa_enabled' => true,
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'geolocation_enabled' => false,
            ], $attrs));

            $dashUrl = $user->dashboardUrl();

            // 1. Role dashboard must return 200 and contain zero hardcoded fake link badges
            $dashResponse = $this->actingAs($user)->get($dashUrl);
            $dashResponse->assertOk();
            $dashHtml = $dashResponse->getContent();
            $this->assertStringNotContainsString('class="link-badge', $dashHtml, "Hardcoded sidebar badge found on {$label} dashboard");

            $sidebarLinks = $this->extractSidebarLinks($dashHtml);
            $this->assertNotEmpty($sidebarLinks, "No sidebar links found on {$label} dashboard");

            foreach ($sidebarLinks as $href) {
                $path = parse_url($href, PHP_URL_PATH) ?: $href;
                $res = $this->actingAs($user)->get($path);
                $this->assertTrue(
                    in_array($res->getStatusCode(), [200, 302], true),
                    "Role [{$label}] sidebar link [{$path}] returned HTTP {$res->getStatusCode()}"
                );
            }

            // 2. Profile page must load 200, link back to $dashUrl, and every sidebar link on profile must be authorized
            $profileResponse = $this->actingAs($user)->get(route('user.profile'));
            $profileResponse->assertOk();
            $profileHtml = $profileResponse->getContent();
            $this->assertStringContainsString($dashUrl, $profileHtml, "Profile page for [{$label}] does not link to {$dashUrl}");

            foreach ($this->extractSidebarLinks($profileHtml) as $href) {
                $path = parse_url($href, PHP_URL_PATH) ?: $href;
                $res = $this->actingAs($user)->get($path);
                $this->assertTrue(
                    in_array($res->getStatusCode(), [200, 302], true),
                    "Role [{$label}] profile sidebar link [{$path}] returned HTTP {$res->getStatusCode()}"
                );
            }
        }
    }

    public function test_zonal_commander_and_reviewers_can_preview_and_download_in_scope_submission(): void
    {
        $profiles = $this->roleProfiles();
        $makeUser = fn (string $key) => User::create(array_merge([
            'name' => ucwords(str_replace('_', ' ', $key)),
            'email_verified_at' => now(),
            'password' => Hash::make('Secure@12'),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'geolocation_enabled' => false,
        ], $profiles[$key]));

        $stateUser = $makeUser('state_user');
        $deskAdmin = $makeUser('desk_admin');
        $zonalCommander = $makeUser('zonal_commander');
        $hqAdmin = $makeUser('hq_admin');
        $superAdmin = $makeUser('super_admin');

        $application = \App\Services\SubmissionWorkflow::create($stateUser, [
            'period' => now()->format('Y-m'),
            'reporting_officer' => $stateUser->name,
            'command_name' => 'Lagos',
            'local_revenue_passport' => '100',
        ]);

        // Desk Admin previews, approves (without optional note), and sees it in Approved Submissions
        $this->actingAs($deskAdmin)->get(route('desk.admin.submissions.show', $application))->assertOk();
        $this->actingAs($deskAdmin)->patch(route('desk.admin.submissions.approve', $application))->assertRedirect();
        $this->actingAs($deskAdmin)->get(route('user.desk.home'))
            ->assertOk()
            ->assertSee('Approved');

        // Zonal Commander previews, downloads CSV/PDF, accesses reports, and approves without 403
        $this->actingAs($zonalCommander)->get(route('user.zonal.home'))->assertOk()->assertSee('Preview');
        $this->actingAs($zonalCommander)->get(route('desk.admin.submissions.show', $application))->assertOk();
        $this->actingAs($zonalCommander)->get(route('desk.admin.submissions.download', $application))->assertOk();
        $this->actingAs($zonalCommander)->get(route('user.submissions.pdf', $application))->assertOk();
        $this->actingAs($zonalCommander)->get(route('desk.admin.reports'))->assertOk();
        $this->actingAs($zonalCommander)->patch(route('zonal.submissions.approve', $application))->assertRedirect();

        // HQ Admin and Super Admin can also preview the submission without 403
        $this->actingAs($hqAdmin)->get(route('desk.admin.submissions.show', $application))->assertOk();
        $this->actingAs($superAdmin)->get(route('desk.admin.submissions.show', $application))->assertOk();
    }
}
