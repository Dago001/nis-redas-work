<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StateCombinedFormTest extends TestCase
{
    use RefreshDatabase;

    private function disableAbac(): void
    {
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    private function stateUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ], $overrides));
    }

    public function test_combined_form_renders_all_ten_directorate_sections(): void
    {
        $this->disableAbac();

        $response = $this->actingAs($this->stateUser())->get(route('user.returns.create'));

        $response->assertOk();

        foreach (['hrm', 'prs', 'finance', 'investigation', 'passport', 'visa', 'migration', 'border', 'ict', 'works-logistics'] as $slug) {
            $response->assertSee('id="dir-'.$slug.'"', false);
        }

        // Spot-check one inner tab/section marker per directorate partial.
        foreach ([
            'id="tab-hrm-cadre"',
            'id="tab-prs-cadre"',
            'id="tab-ict-cadre"',
            'id="tab-visa-cadre"',
            'id="tab-passport-meta"',
            'id="tab-migration-staff"',
            'id="tab-works-logistics-cadre"',
            'id="tab-border-personnel"',
            'id="investigation-section-1"',
            'name="local_revenue_passport"',
        ] as $marker) {
            $response->assertSee($marker, false);
        }

        // The state layout owns consent + attachments; the embedded investigation
        // declaration must be suppressed (exactly one required consent checkbox).
        $this->assertSame(1, substr_count($response->getContent(), '<input type="checkbox" name="data_consent"'));
        $response->assertSee('name="attachments[]"', false);
        $response->assertSee('name="command_name"', false);
    }

    public function test_combined_form_submission_stores_namespaced_return_data(): void
    {
        $this->disableAbac();

        $user = $this->stateUser();

        $response = $this->actingAs($user)->post(route('user.returns.store'), [
            'command_name' => 'Lagos',
            'period' => '2026-09',
            'return_type' => 'monthly',
            'reporting_officer' => 'Test Officer',
            'data_consent' => '1',
            'hrm' => ['general_report' => ['challenges' => 'HRM challenge text']],
            'passport' => ['staff_strength' => [['male' => 5, 'female' => 3, 'total' => 8]]],
            'works' => ['staff_strength' => [['cadre' => 'comptroller', 'male' => 2, 'female' => 1, 'total' => 3]]],
            'border' => ['general' => ['security' => 'Border security report']],
            'local_revenue_passport' => '1500',
        ]);

        $response->assertRedirect('/user/submissions');

        $application = Application::query()->where('user_id', $user->id)->latest('id')->first();

        $this->assertNotNull($application);
        $this->assertSame('state', $application->category);
        $this->assertSame('LA', $application->scope_code);
        $this->assertSame('desk_review', $application->workflow_stage);
        $this->assertSame('pending', $application->status);
        $this->assertSame('HRM challenge text', $application->return_data['hrm']['general_report']['challenges'] ?? null);
        $this->assertSame(8, $application->return_data['passport']['staff_strength'][0]['total'] ?? null);
        $this->assertSame(3, $application->return_data['works']['staff_strength'][0]['total'] ?? null);
        $this->assertSame('Border security report', $application->return_data['border']['general']['security'] ?? null);
        $this->assertSame('1500', $application->return_data['local_revenue_passport'] ?? null);
    }

    public function test_preview_page_renders_entered_values_without_persisting(): void
    {
        $this->disableAbac();

        $user = $this->stateUser();

        $response = $this->actingAs($user)->post(route('user.returns.preview'), [
            'command_name' => 'Lagos',
            'period' => '2026-09',
            'return_type' => 'monthly',
            'reporting_officer' => 'Preview Officer',
            'data_consent' => '1',
            'hrm' => ['general_report' => ['challenges' => 'Unique preview marker text']],
        ]);

        $response->assertOk();
        $response->assertSee('Preview Officer');
        $response->assertSee('Unique preview marker text');

        $this->assertSame(0, Application::query()->where('user_id', $user->id)->count());
    }

    public function test_edit_form_prefills_own_pending_submission(): void
    {
        $this->disableAbac();

        $user = $this->stateUser();
        $application = SubmissionWorkflow::create($user, [
            'command_name' => 'Lagos',
            'period' => '2026-09',
            'reporting_officer' => 'Edit Officer',
            'hrm' => ['general_report' => ['challenges' => 'Prefill marker']],
        ]);

        $response = $this->actingAs($user)->get(route('user.returns.edit', $application));

        $response->assertOk();
        $response->assertSee('REDAS_PREFILL', false);
        $response->assertSee('Prefill marker');

        $otherUser = $this->stateUser();
        $this->actingAs($otherUser)->get(route('user.returns.edit', $application))->assertForbidden();
    }

    public function test_hrm_zones_table_shows_only_the_officers_own_zone(): void
    {
        $this->disableAbac();

        // Zone A officer (LASC) should only see ZONE A, never ZONE B..H
        $zoneAUser = $this->stateUser([
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
        ]);

        $resA = $this->actingAs($zoneAUser)->get(route('user.returns.create'));
        $resA->assertOk();
        $resA->assertSee('ZONE A TOTAL');
        $resA->assertSee('Your Command');
        $resA->assertDontSee('ZONE B TOTAL');
        $resA->assertDontSee('ZONE C TOTAL');
        $resA->assertDontSee('ZONE D TOTAL');
        $resA->assertDontSee('ZONE H TOTAL');

        // Own command (LASC) inputs are active & auto-synced from 1. Cadre, while other Zone A commands (e.g., OGSC) are disabled
        $htmlA = $resA->getContent();
        $resA->assertSee('(Auto-filled from 1. Cadre)');
        $this->assertMatchesRegularExpression('/name="hrm\[zones\]\[a\]\[lasc\]\[comptroller\]"[^>]*data-own-command="1"/', $htmlA);
        $this->assertDoesNotMatchRegularExpression('/name="hrm\[zones\]\[a\]\[lasc\]\[comptroller\]"[^>]*disabled/', $htmlA);
        $this->assertMatchesRegularExpression('/name="hrm\[zones\]\[a\]\[ogsc\]\[comptroller\]"[^>]*disabled/', $htmlA);

        // Dynamic rank tables (APU, Recruitment, Career, Upgrading/Conversion, Pension) use official NIS rank dropdowns with duplicate prevention
        foreach (['apu', 'recruitment', 'career', 'upgrading_conversion', 'pension'] as $tableKey) {
            $this->assertStringContainsString("name=\"hrm[{$tableKey}][0][rank]\" class=\"ni ni-select hrm-rank-select\"", $htmlA);
        }
        $this->assertStringContainsString('syncUniqueRankDropdowns', $htmlA);

        // Investigation & Compliance (2. Breach of Immigration Law): only Zone A shown, SHQ & Zone B..H hidden, LASC active & OGSC disabled
        $this->assertStringNotContainsString('name="breach[SHQ]', $htmlA);
        $this->assertStringNotContainsString('name="breach[ZONE_B]', $htmlA);
        $this->assertMatchesRegularExpression('/name="breach\[ZONE_A\]\[LASC\]\[companies\]"[^>]*>/', $htmlA);
        $this->assertDoesNotMatchRegularExpression('/name="breach\[ZONE_A\]\[LASC\]\[companies\]"[^>]*disabled/', $htmlA);
        $this->assertMatchesRegularExpression('/name="breach\[ZONE_A\]\[OGSC\]\[companies\]"[^>]*disabled/', $htmlA);
        $this->assertMatchesRegularExpression('/name="breach\[ZONE_A\]\[_zone\]\[companies\]"[^>]*disabled/', $htmlA);

        // DFU Activities & Suspect Index: officer sees ONLY their own location (LASC), no SHQ, no OGSC, no Zone B
        $this->assertStringContainsString('name="dfu[ZONE_A][LASC][', $htmlA);
        $this->assertStringNotContainsString('name="dfu[SHQ]', $htmlA);
        $this->assertStringNotContainsString('name="dfu[ZONE_A][OGSC]', $htmlA);
        $this->assertStringNotContainsString('name="dfu[ZONE_B]', $htmlA);
        $this->assertStringContainsString('name="suspect[ZONE_A][LASC][', $htmlA);
        $this->assertStringNotContainsString('name="suspect[SHQ]', $htmlA);
        $this->assertStringNotContainsString('name="suspect[ZONE_A][OGSC]', $htmlA);

        // Migration (ANTI-SOM & ANTI-TIP): only Zone A shown, no Add Zone Rows, LASC active & OGSC disabled
        $this->assertStringNotContainsString('id="som-zone-zone_b"', $htmlA);
        $this->assertStringNotContainsString('id="tip-zone-zone_b"', $htmlA);
        $this->assertStringNotContainsString('data-m-action="add-som-zone"', $htmlA);
        $this->assertDoesNotMatchRegularExpression('/name="smuggling_of_migrants\[LASC\]\[migrants_intercepted\]"[^>]*disabled/', $htmlA);
        $this->assertMatchesRegularExpression('/name="smuggling_of_migrants\[OGSC\]\[migrants_intercepted\]"[^>]*disabled/', $htmlA);
        $this->assertDoesNotMatchRegularExpression('/name="trafficking_in_persons\[LASC\]\[victims_rescued\]"[^>]*disabled/', $htmlA);
        $this->assertMatchesRegularExpression('/name="trafficking_in_persons\[OGSC\]\[victims_rescued\]"[^>]*disabled/', $htmlA);

        // Zone D officer (FCTC) should only see ZONE D, never ZONE A
        $zoneDUser = $this->stateUser([
            'primary_location_code' => 'FCTC',
            'assigned_state_code' => 'FCTC',
            'assigned_zonal_command_code' => 'ZONE-D',
        ]);

        $resD = $this->actingAs($zoneDUser)->get(route('user.returns.create'));
        $resD->assertOk();
        $resD->assertSee('ZONE D TOTAL');
        $resD->assertSee('RMAT');
        $resD->assertDontSee('ZONE A TOTAL');
        $resD->assertDontSee('ZONE B TOTAL');
    }

    public function test_passport_executive_summary_only_shows_centers_in_officers_own_command(): void
    {
        $this->disableAbac();
        $this->seed(\Database\Seeders\NisDirectorySeeder::class);

        $lagosOfficer = $this->stateUser([
            'primary_location_code' => 'LASC',
            'assigned_state_code' => 'LASC',
            'assigned_zonal_command_code' => 'ZONE-A',
        ]);

        $res = $this->actingAs($lagosOfficer)->get(route('user.returns.create'));
        $res->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('const allCenters =', $html);
        preg_match('/const allCenters = (\[.*?\]);/', $html, $m);
        $this->assertNotEmpty($m[1] ?? '');
        $centers = json_decode($m[1], true);

        $this->assertContains('Alausa', $centers);
        $this->assertContains('Festac', $centers);
        $this->assertContains('Alimosho', $centers);
        $this->assertContains('Ikoyi', $centers);
        $this->assertNotContains('Abakaliki', $centers);
        $this->assertNotContains('Kano', $centers);
        $this->assertNotContains('Enugu', $centers);

        // Foreign Missions tab is Directorate of Passport only (removed from command returns)
        $res->assertDontSee('Passport Issued in Foreign Missions');
        $res->assertDontSee('id="tab-passport-foreign"', false);
    }
}
