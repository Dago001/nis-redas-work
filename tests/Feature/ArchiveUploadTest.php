<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ArchiveDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_archive_upload_persists_to_private_storage_and_enforces_authorization_and_integrity(): void
    {
        Storage::fake('local');

        $officerA = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        $officerB = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'KN',
            'access_level' => 0,
        ]);

        $deskAdminLA = User::factory()->create([
            'role' => 'state',
            'user_category' => 'desk_admin',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 1,
        ]);

        $pdf = UploadedFile::fake()->createWithContent('secret-return.pdf', "%PDF-1.4\n%test content\n%%EOF");

        $this->actingAs($officerA)->post(route('user.archive.store'), [
            'title' => 'November 2026 Nominal Roll',
            'doc_type' => 'nominal_roll',
            'period' => '2026-11',
            'description' => 'Official archive copy',
            'documents' => [$pdf],
            'data_consent' => '1',
        ])->assertRedirect(route('user.archive'));

        $doc = ArchiveDocument::query()->first();
        $this->assertNotNull($doc);
        $this->assertNotSame('secret-return.pdf', basename($doc->stored_path));
        $this->assertTrue(Storage::disk('local')->exists($doc->stored_path));
        $this->assertSame(64, strlen($doc->sha256));

        // Owner downloads successfully
        $dl = $this->actingAs($officerA)->get(route('user.archive.download', $doc));
        $dl->assertOk();
        $this->assertStringContainsString('%PDF-1.4', $dl->streamedContent());

        // In-scope desk admin can download
        $this->actingAs($deskAdminLA)->get(route('user.archive.download', $doc))->assertOk();

        // Another officer is forbidden
        $this->actingAs($officerB)->get(route('user.archive.download', $doc))->assertForbidden();

        // Tampered file fails integrity check with 409
        Storage::disk('local')->put($doc->stored_path, 'tampered bytes');
        $this->actingAs($officerA)->get(route('user.archive.download', $doc))->assertStatus(409);
    }

    public function test_disallowed_file_extension_fake_mime_and_path_traversal_are_rejected(): void
    {
        Storage::fake('local');

        $officer = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        // PHP shell upload rejected
        $phpFile = UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;');
        $this->actingAs($officer)->post(route('user.archive.store'), [
            'title' => 'Bad PHP',
            'doc_type' => 'other',
            'documents' => [$phpFile],
            'data_consent' => '1',
        ])->assertSessionHasErrors('documents.0');

        // Fake PDF containing executable/text content rejected by MIME check
        $fakePdf = UploadedFile::fake()->createWithContent('malware.pdf', 'MZ plain text not a pdf');
        $this->actingAs($officer)->post(route('user.archive.store'), [
            'title' => 'Fake PDF',
            'doc_type' => 'other',
            'documents' => [$fakePdf],
            'data_consent' => '1',
        ])->assertSessionHasErrors();

        // Path traversal in crafted return_data rejected with 404
        $app = Application::create([
            'user_id' => $officer->id,
            'status' => 'pending',
            'workflow_stage' => 'desk_review',
            'category' => 'state',
            'scope_code' => 'LA',
            'return_data' => ['attachments' => ['../../.env']],
        ]);

        $this->actingAs($officer)
            ->get(route('user.returns.document', [$app, 'attachments', 0]))
            ->assertNotFound();
    }
}
