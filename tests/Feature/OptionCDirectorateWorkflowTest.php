<?php

namespace Tests\Feature;

use App\Models\ConsolidatedReport;
use App\Models\ReportingUnit;
use App\Models\User;
use App\Services\Reporting\CompletenessService;
use App\Services\Reporting\ConsolidationService;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OptionCDirectorateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_four_sections_plus_one_directorate_consolidated_gives_prs_five_records(): void
    {
        $hrm = ReportingUnit::create([
            'code' => 'hrm',
            'name' => 'Human Resources Management (HRM)',
            'type' => 'directorate',
            'is_active' => true,
        ]);

        $sectionCodes = ['hrm-recruit', 'hrm-discipline', 'hrm-training', 'hrm-welfare'];
        $apps = [];

        foreach ($sectionCodes as $secCode) {
            ReportingUnit::create([
                'code' => $secCode,
                'name' => strtoupper($secCode),
                'type' => 'section',
                'parent_id' => $hrm->id,
                'is_active' => true,
            ]);

            $officer = User::factory()->create([
                'role' => 'directorate',
                'user_category' => 'directorate_user',
                'primary_location_type' => 'directorate',
                'primary_location_code' => 'HRM',
                'assigned_directorate_code' => 'HRM',
                'assigned_section_code' => $secCode,
                'access_level' => 0,
            ]);

            $apps[] = SubmissionWorkflow::create($officer, [
                'directorate_slug' => 'hrm',
                'report_period' => '2026-11',
                'staff_count' => 25,
            ]);
        }

        $hrmAdmin = User::factory()->create([
            'role' => 'admin',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'HRM',
            'assigned_directorate_code' => 'HRM',
            'access_level' => 3,
        ]);

        $consolidated = ConsolidationService::createDraft(
            actor: $hrmAdmin,
            level: ConsolidatedReport::LEVEL_DIRECTORATE,
            scopeCode: 'hrm',
            period: '2026-11',
            includeIds: array_map(fn ($a) => $a->id, $apps),
            exclusions: [],
            narrative: 'HRM Directorate consolidated report.'
        );

        $this->assertEquals(100.0, $consolidated->latestRevision->aggregated_data['staff_count']['total']);

        $summary = CompletenessService::summary('2026-11', ['directorate' => 'hrm']);
        $this->assertSame(4, $summary['totals']['expected_originals']);
        $this->assertSame(4, $summary['totals']['received_originals']);
        $this->assertSame(1, $summary['totals']['received_consolidated']);
        $this->assertSame(5, $summary['totals']['prs_records_total']);

        // Cross-directorate admin cannot inspect or revise HRM's consolidated report
        $ictAdmin = User::factory()->create([
            'role' => 'admin',
            'user_category' => 'directorate_admin',
            'primary_location_type' => 'directorate',
            'primary_location_code' => 'ICT',
            'assigned_directorate_code' => 'ICT',
            'access_level' => 3,
        ]);

        $this->actingAs($ictAdmin)->get(route('consolidated.show', $consolidated))->assertForbidden();
        $this->actingAs($ictAdmin)->get(route('consolidated.edit', $consolidated))->assertForbidden();
    }
}
