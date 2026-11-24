<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('settings.abac_enabled', false);
        Cache::put('settings.abac_location_enforcement', false);
        Cache::put('settings.abac_hq_bypass', true);
    }

    public function test_duplicate_submission_with_same_idempotency_key_creates_single_application(): void
    {
        $officer = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        $key = (string) Str::uuid();

        $first = SubmissionWorkflow::create($officer, ['command_name' => 'Lagos', 'report_period' => '2026-11'], $key);
        $second = SubmissionWorkflow::create($officer, ['command_name' => 'Lagos', 'report_period' => '2026-11'], $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Application::query()->count());
    }

    public function test_deleting_user_with_submitted_applications_is_blocked(): void
    {
        $officer = User::factory()->create([
            'role' => 'officer',
            'user_category' => 'state_user',
            'primary_location_type' => 'state',
            'primary_location_code' => 'LA',
            'access_level' => 0,
        ]);

        SubmissionWorkflow::create($officer, ['command_name' => 'Lagos', 'report_period' => '2026-11']);

        $this->expectException(LogicException::class);
        $officer->delete();
    }
}
