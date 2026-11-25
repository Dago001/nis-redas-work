<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ConsolidatedReport;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\Reporting\CompletenessService;
use App\Services\Reporting\ConsolidationService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OptionCZonalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_five_formations_plus_one_zonal_consolidated_gives_prs_six_separate_records(): void
    {
        $zone = ReportingUnit::create([
            'code' => 'ZONE-A',
            'name' => 'Lagos Zonal HQ',
            'type' => 'zone',
            'is_active' => true,
        ]);

        $formationCodes = ['LA', 'OG', 'OY', 'OS', 'ON'];
        $apps = [];

        foreach ($formationCodes as $idx => $code) {
            ReportingUnit::create([
                'code' => $code,
                'name' => "{$code} State Command",
                'type' => 'formation',
                'formation_type' => 'state_command',
                'parent_id' => $zone->id,
                'is_active' => true,
            ]);

            $officer = User::factory()->create([
                'role' => 'officer',
                'user_category' => 'state_user',
                'primary_location_type' => 'state',
                'primary_location_code' => $code,
                'access_level' => 0,
            ]);

            $desk = User::factory()->create([
                'role' => 'state',
                'user_category' => 'desk_admin',
                'primary_location_type' => 'state',
                'primary_location_code' => $code,
                'assigned_zonal_command_code' => 'ZONE-A',
                'access_level' => 1,
            ]);

            $app = SubmissionWorkflow::create($officer, [
                'command_name' => "{$code} State Command",
                'report_period' => '2026-11',
                'passport' => ['issued' => 100 + ($idx * 10)],
            ]);

            SubmissionWorkflow::approve($app, $desk);
            $apps[] = $app->fresh(['currentVersion']);
        }

        $zonalCommander = User::factory()->create([
            'role' => 'zonal',
            'user_category' => 'zonal_commander',
            'primary_location_type' => 'zonal',
            'primary_location_code' => 'ZONE-A',
            'access_level' => 4,
        ]);

        // Include first 4 formations, exclude 5th with mandatory reason
        $includeIds = array_map(fn (Application $a) => $a->id, array_slice($apps, 0, 4));
        $excludedApp = $apps[4];

        $consolidated = ConsolidationService::createDraft(
            actor: $zonalCommander,
            level: ConsolidatedReport::LEVEL_ZONAL,
            scopeCode: 'ZONE-A',
            period: '2026-11',
            includeIds: $includeIds,
            exclusions: [$excludedApp->id => 'Pending clarification on border figures'],
            narrative: 'Zone A November 2026 synthesis.'
        );

        $this->assertSame(1, $consolidated->current_revision_no);
        $this->assertCount(5, $consolidated->latestRevision->sources);

        // Sum of first 4: 100 + 110 + 120 + 130 = 460
        $agg = $consolidated->latestRevision->aggregated_data;
        $this->assertEquals(460.0, $agg['passport.issued']['total']);
        $this->assertSame(4, $agg['passport.issued']['sources_count']);

        // Originals remain intact and independently stored
        $this->assertSame(5, Application::query()->where('report_period', '2026-11')->count());

        // Submit and approve consolidated report at HQ
        ConsolidationService::submit($consolidated, $zonalCommander);
        $hqAdmin = User::factory()->create([
            'role' => 'admin',
            'user_category' => 'hq_admin',
            'primary_location_type' => 'headquarters',
            'primary_location_code' => 'HQ',
            'access_level' => 5,
        ]);
        ConsolidationService::approve($consolidated, $hqAdmin);

        // PRS dashboard sees 5 originals + 1 consolidated = 6 distinct records
        $prsAdmin = User::factory()->create([
            'role' => 'admin',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'PRS',
            'assigned_directorate_code' => 'PRS',
            'access_level' => 3,
        ]);

        $summary = CompletenessService::summary('2026-11', ['zone' => 'ZONE-A']);
        $this->assertSame(5, $summary['totals']['expected_originals']);
        $this->assertSame(5, $summary['totals']['received_originals']);
        $this->assertSame(1, $summary['totals']['received_consolidated']);
        $this->assertSame(6, $summary['totals']['prs_records_total']);
        $this->assertSame(100.0, $summary['totals']['completion_pct']);

        $this->actingAs($prsAdmin)->get(route('prs.dashboard', ['period' => '2026-11', 'zone' => 'ZONE-A']))
            ->assertOk()
            ->assertSee($consolidated->reference)
            ->assertSee('RET-'.str_pad((string) $apps[0]->id, 5, '0', STR_PAD_LEFT));

        // PRS can inspect each original AND the consolidated report independently
        $this->actingAs($prsAdmin)->get(route('prs.originals.show', $apps[0]))->assertOk();
        $this->actingAs($prsAdmin)->get(route('prs.consolidated.show', $consolidated))->assertOk();

        // Removing 1 original drops completion to 80% - consolidated report does NOT replace originals
        $apps[0]->delete();
        $afterDelete = CompletenessService::summary('2026-11', ['zone' => 'ZONE-A']);
        $this->assertSame(4, $afterDelete['totals']['received_originals']);
        $this->assertSame(1, $afterDelete['totals']['received_consolidated']);
        $this->assertSame(80.0, $afterDelete['totals']['completion_pct']);
    }
}
