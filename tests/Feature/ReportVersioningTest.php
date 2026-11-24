<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ReportIndicatorValue;
use App\Models\User;
use App\Services\Reporting\IndicatorExtractor;
use App\Services\Reporting\ReportVersionBackfill;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReportVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_indicator_extractor_parses_numbers_and_ignores_phone_like_strings(): void
    {
        $extracted = IndicatorExtractor::extract([
            'command_name' => 'Lagos',
            'report_period' => '2026-11',
            'contact_phone' => '08012345678',
            'passport' => [
                'male' => '42',
                'female' => 38,
                'revenue' => '12500.50',
                'notes' => 'Some text',
            ],
        ]);

        $this->assertArrayNotHasKey('contact_phone', $extracted);
        $this->assertArrayNotHasKey('report_period', $extracted);
        $this->assertEquals(42.0, $extracted['passport.male']['value']);
        $this->assertSame('integer', $extracted['passport.male']['type']);
        $this->assertEquals(12500.5, $extracted['passport.revenue']['value']);
        $this->assertSame('decimal', $extracted['passport.revenue']['type']);
    }

    public function test_resubmission_creates_v2_preserves_v1_and_flips_is_current(): void
    {
        $officer = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        $app = SubmissionWorkflow::create($officer, [
            'command_name' => 'Lagos',
            'report_period' => '2026-11',
            'arrivals' => 10,
        ]);

        $this->assertSame(1, $app->current_version);
        $v1 = $app->versions()->where('version_no', 1)->firstOrFail();

        $this->actingAs($officer)->put(route('user.returns.update', $app), [
            'command_name' => 'Lagos',
            'period' => '2026-11',
            'return_type' => 'monthly',
            'reporting_officer' => $officer->name,
            'data_consent' => '1',
            'arrivals' => 25,
        ])->assertRedirect();

        $app->refresh();
        $this->assertSame(2, $app->current_version);
        $this->assertSame(10, $v1->fresh()->return_data['arrivals']);

        $v1Val = ReportIndicatorValue::query()->where('report_version_id', $v1->id)->firstOrFail();
        $this->assertFalse($v1Val->is_current);

        $v2 = $app->versions()->where('version_no', 2)->firstOrFail();
        $v2Val = ReportIndicatorValue::query()->where('report_version_id', $v2->id)->firstOrFail();
        $this->assertTrue($v2Val->is_current);
        $this->assertEquals(25.0, $v2Val->value);
    }

    public function test_backfill_creates_v1_for_legacy_applications(): void
    {
        $officer = User::factory()->create();
        $legacy = Application::create([
            'user_id' => $officer->id,
            'status' => 'approved',
            'workflow_stage' => 'approved',
            'category' => 'state',
            'scope_code' => 'LA',
            'return_data' => ['report_period' => '2025-12', 'total' => 99],
            'current_version' => 0,
        ]);

        $count = ReportVersionBackfill::run();
        $this->assertGreaterThanOrEqual(1, $count);

        $legacy->refresh();
        $this->assertSame(1, $legacy->current_version);
        $this->assertDatabaseHas('report_versions', [
            'application_id' => $legacy->id,
            'version_no' => 1,
            'reason' => 'backfill',
        ]);
    }
}
