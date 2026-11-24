<?php

namespace Tests\Feature;

use App\Models\ConsolidatedReport;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\Reporting\ConsolidationService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConsolidatedStalenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_source_edit_flags_consolidated_report_stale_and_preserves_version_history(): void
    {
        $zone = ReportingUnit::create(['code' => 'ZONE-A', 'name' => 'Lagos Zonal HQ', 'type' => 'zone', 'is_active' => true]);
        ReportingUnit::create(['code' => 'LA', 'name' => 'Lagos', 'type' => 'formation', 'parent_id' => $zone->id, 'is_active' => true]);

        $officer = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        $zonal = User::factory()->create([
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'access_level' => 4,
        ]);

        $hq = User::factory()->create([
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 5,
        ]);

        $app = SubmissionWorkflow::create($officer, [
            'command_name' => 'Lagos',
            'report_period' => '2026-11',
            'passports_issued' => 50,
        ]);

        $report = ConsolidationService::createDraft($zonal, ConsolidatedReport::LEVEL_ZONAL, 'ZONE-A', '2026-11', [$app->id], [], 'Rev 1 narrative');
        ConsolidationService::submit($report, $zonal);
        ConsolidationService::approve($report, $hq);
        $report->refresh();
        $this->assertSame(1, $report->approved_revision_no);

        // Officer updates pending submission -> creates v2 -> flags consolidated report stale
        $this->actingAs($officer)->put(route('user.returns.update', $app), [
            'command_name' => 'Lagos',
            'period' => '2026-11',
            'return_type' => 'monthly',
            'reporting_officer' => $officer->name,
            'data_consent' => '1',
            'passports_issued' => 75,
        ])->assertRedirect();

        $app->refresh();
        $report->refresh();

        $this->assertSame(2, $app->current_version);
        $this->assertTrue($report->is_stale);

        // Revision 1 still references version 1 untouched (50, not 75)
        $rev1 = $report->revisions()->where('revision_no', 1)->firstOrFail();
        $this->assertEquals(50.0, $rev1->aggregated_data['passports_issued']['total']);
        $this->assertSame(1, $rev1->sources()->first()->reportVersion->version_no);

        // Submitting while stale must be blocked
        $report->update(['status' => ConsolidatedReport::STATUS_DRAFT]);
        try {
            ConsolidationService::submit($report, $zonal);
            $this->fail('Expected stale consolidated report submission to fail with 409');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        // Revise -> Revision 2 references version 2 (75), clears stale, preserves Revision 1 and approved_revision_no
        $revised = ConsolidationService::revise($report, $zonal, [$app->id], [], 'Rev 2 narrative', 'Updated after source edit');
        $this->assertFalse($revised->is_stale);
        $this->assertSame(2, $revised->current_revision_no);
        $this->assertSame(1, $revised->approved_revision_no);
        $this->assertEquals(75.0, $revised->latestRevision->aggregated_data['passports_issued']['total']);
        $this->assertSame(2, $revised->revisions()->count());
    }
}
