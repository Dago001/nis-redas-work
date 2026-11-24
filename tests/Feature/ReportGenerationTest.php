<?php

namespace Tests\Feature;

use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_csv_excel_and_pdf_report_generation_respects_scope_and_blocks_formula_injection(): void
    {
        Storage::fake('local');

        $officerA = User::factory()->create([
            'name' => '=HYPERLINK("http://evil.example","Click")',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        $officerB = User::factory()->create([
            'name' => 'Secret Kano Officer',
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'KN',
            'access_level' => 0,
        ]);

        $appA = SubmissionWorkflow::create($officerA, [
            'command_name' => 'Lagos Command',
            'report_period' => '2026-11',
            'reporting_officer' => '=HYPERLINK("http://evil.example","Click")',
            'passports' => ['issued' => 42],
        ]);

        SubmissionWorkflow::create($officerB, [
            'command_name' => 'Kano Command',
            'report_period' => '2026-11',
            'reporting_officer' => 'Secret Kano Officer',
            'passports' => ['issued' => 999],
        ]);

        // 1. CSV report generation + formula injection protection + scope isolation
        $csvRes = $this->actingAs($officerA)->post(route('user.reports.generate'), [
            'report_type' => 'submission_summary',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'format' => 'csv',
        ]);
        $csvReport = GeneratedReport::query()->where('format', 'csv')->latest('id')->firstOrFail();
        $csvRes->assertRedirect(route('user.reports.download', $csvReport));

        $csvContent = $this->actingAs($officerA)->get(route('user.reports.download', $csvReport))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK(", $csvContent);
        $this->assertStringNotContainsString('Secret Kano Officer', $csvContent);

        // 2. Excel report generation with numeric typing (t="n")
        $this->actingAs($officerA)->post(route('user.reports.generate'), [
            'report_type' => 'full_export',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'format' => 'excel',
        ])->assertRedirect();

        $xlsxReport = GeneratedReport::query()->where('format', 'excel')->latest('id')->firstOrFail();
        $this->assertSame('completed', $xlsxReport->status);

        $tmp = tempnam(sys_get_temp_dir(), 'test-xlsx-');
        file_put_contents($tmp, Storage::disk('local')->get($xlsxReport->stored_path));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString('Indicator Code', $sheetXml);
        $this->assertStringContainsString('t="n"', $sheetXml);
        $this->assertStringContainsString('<v>42</v>', $sheetXml);

        // 3. PDF report generation produces real %PDF binary
        $this->actingAs($officerA)->post(route('user.reports.generate'), [
            'report_type' => 'submission_summary',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'format' => 'pdf',
        ])->assertRedirect();

        $pdfReport = GeneratedReport::query()->where('format', 'pdf')->latest('id')->firstOrFail();
        $pdfBytes = Storage::disk('local')->get($pdfReport->stored_path);
        $this->assertStringStartsWith('%PDF', $pdfBytes);

        // 4. Desk admin CSV export also neutralizes formulas
        $deskAdmin = User::factory()->create([
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 1,
        ]);

        $deskCsv = $this->actingAs($deskAdmin)
            ->get(route('desk.admin.submissions.download', $appA))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(", $deskCsv);
    }
}
