<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLogIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_is_immutable_and_redacts_sensitive_fields(): void
    {
        $user = User::factory()->create();

        $entry = AuditLogger::log(
            user: $user,
            action: 'test.sensitive',
            status: 'success',
            entityType: 'user',
            entityId: $user->id,
            details: [
                'password' => 'SuperSecret123!',
                'otp' => '998877',
                'nested' => [
                    'access_token' => 'eyJ-secret',
                    'safe_field' => 'ok',
                ],
            ]
        );

        $this->assertSame('[REDACTED]', $entry->details['password']);
        $this->assertSame('[REDACTED]', $entry->details['otp']);
        $this->assertSame('[REDACTED]', $entry->details['nested']['access_token']);
        $this->assertSame('ok', $entry->details['nested']['safe_field']);
        $this->assertNotNull($entry->entry_hash);

        try {
            $entry->update(['action' => 'tampered']);
            $this->fail('Expected LogicException when updating AuditLog');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_audit_verify_command_passes_for_intact_chain_and_fails_after_raw_tamper(): void
    {
        $user = User::factory()->create();

        AuditLogger::log($user, 'test.one', 'success', 'user', $user->id);
        $second = AuditLogger::log($user, 'test.two', 'success', 'user', $user->id);

        $this->artisan('redas:audit:verify')->assertExitCode(0);

        // Tamper directly in DB bypassing Eloquent model guard
        DB::table('audit_logs')->where('id', $second->id)->update(['action' => 'tampered.action']);

        $this->artisan('redas:audit:verify')->assertExitCode(1);
    }
}
