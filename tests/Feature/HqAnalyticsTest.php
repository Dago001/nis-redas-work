<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\User;
use App\Services\SettingService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HqAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::set('abac_enabled', false, 'boolean');
    }

    private function createUser(array $attrs): User
    {
        return User::create(array_merge([
            'name' => 'Test Officer',
            'email_verified_at' => now(),
            'password' => Hash::make('Secure@12'),
            'is_enabled' => true,
            'must_change_password' => false,
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'geolocation_enabled' => false,
        ], $attrs));
    }

    public function test_hq_analytics_renders_live_metrics_filters_and_csv_export(): void
    {
        $hqAdmin = $this->createUser([
            'name' => 'HQ Analyst',
            'service_number' => '81001',
            'email' => 'hq.analyst@nis.gov.ng',
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 5,
        ]);

        $stateOfficer = $this->createUser([
            'name' => 'Lagos Officer',
            'service_number' => '81002',
            'email' => 'lagos.officer@nis.gov.ng',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
            'access_level' => 0,
        ]);

        $dirOfficer = $this->createUser([
            'name' => 'HRM Officer',
            'service_number' => '81003',
            'email' => 'hrm.officer@nis.gov.ng',
            'role' => 'directorate',
            'user_category' => 'directorate_user',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'hrm',
            'assigned_directorate_code' => 'hrm',
            'access_level' => 0,
        ]);

        // Seed 1 approved state return in ZONE-A and 1 pending directorate return in HRM
        Application::create([
            'user_id' => $stateOfficer->id,
            'status' => 'approved',
            'workflow_stage' => SubmissionWorkflow::STAGE_APPROVED,
            'category' => SubmissionWorkflow::CATEGORY_STATE,
            'report_period' => '2026-11',
            'scope_code' => 'LASC',
            'zonal_code' => 'ZONE-A',
            'return_data' => [
                'report_period' => '2026-11',
                'hrm_total_staff' => '120',
                'passport_issued' => '450',
            ],
            'workflow_path' => [
                ['stage' => SubmissionWorkflow::STAGE_SUBMITTED, 'at' => now()->subHours(10)->toIso8601String()],
                ['stage' => SubmissionWorkflow::STAGE_ZONAL_REVIEW, 'at' => now()->subHours(6)->toIso8601String()],
                ['stage' => SubmissionWorkflow::STAGE_APPROVED, 'at' => now()->subHours(2)->toIso8601String()],
            ],
        ]);

        Application::create([
            'user_id' => $dirOfficer->id,
            'status' => 'pending',
            'workflow_stage' => SubmissionWorkflow::STAGE_HQ_REVIEW,
            'category' => SubmissionWorkflow::CATEGORY_DIRECTORATE,
            'report_period' => '2026-11',
            'scope_code' => 'hrm',
            'return_data' => [
                'report_period' => '2026-11',
                'directorate_slug' => 'hrm',
            ],
        ]);

        ConsolidatedReport::create([
            'reference' => 'CON-ZN-ZONE-A-2026-11',
            'level' => ConsolidatedReport::LEVEL_ZONAL,
            'scope_code' => 'ZONE-A',
            'report_period' => '2026-11',
            'title' => 'Zone A November 2026 Consolidated Return',
            'status' => ConsolidatedReport::STATUS_SUBMITTED,
            'workflow_stage' => SubmissionWorkflow::STAGE_HQ_REVIEW,
            'current_revision_no' => 1,
            'created_by' => $hqAdmin->id,
        ]);

        // Main analytics view loads with live data and page-managed chart canvases
        $res = $this->actingAs($hqAdmin)->get(route('admin.hq.analytics'));
        $res->assertOk()
            ->assertSee('National Analytics')
            ->assertSee('CON-ZN-ZONE-A-2026-11')
            ->assertSee('ZONE-A')
            ->assertSee('LASC')
            ->assertSee('data-chart-managed="page"', false)
            ->assertSee('50%'); // 1 approved out of 2

        // Filtered view by zone=ZONE-A narrows to 1 approved return (100%)
        $filtered = $this->actingAs($hqAdmin)->get(route('admin.hq.analytics', [
            'zone' => 'ZONE-A',
            'period' => '2026-11',
            'category' => 'state',
            'status' => 'approved',
        ]));
        $filtered->assertOk()
            ->assertSee('100%')
            ->assertSee('LASC');

        // CSV export returns downloadable CSV containing live sections
        $csv = $this->actingAs($hqAdmin)->get(route('admin.hq.analytics.export', ['period' => '2026-11']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('EXECUTIVE KPI SUMMARY', $body);
        $this->assertStringContainsString('ZONAL COMMAND MATRIX', $body);
        $this->assertStringContainsString('FORMATION LEADERBOARD', $body);
        $this->assertStringContainsString('LASC', $body);

        // Non-HQ user cannot access HQ analytics or export
        $this->actingAs($stateOfficer)->get(route('admin.hq.analytics'))->assertForbidden();
        $this->actingAs($stateOfficer)->get(route('admin.hq.analytics.export'))->assertForbidden();
    }
}
