<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class VerifyAuditLogCommand extends Command
{
    protected $signature = 'redas:audit:verify';

    protected $description = 'Verify the cryptographic hash chain of audit_logs entries';

    public function handle(): int
    {
        $checked = 0;
        $expectedPrev = str_repeat('0', 64);
        $firstBreak = null;

        AuditLog::query()
            ->whereNotNull('entry_hash')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$checked, &$expectedPrev, &$firstBreak): bool {
                foreach ($rows as $row) {
                    $checked++;
                    if ($checked > 1 && $row->prev_hash !== $expectedPrev) {
                        $firstBreak = "Audit log #{$row->id}: prev_hash mismatch (chain discontinuity).";

                        return false;
                    }

                    $recomputed = AuditLogger::computeHash(
                        prevHash: (string) $row->prev_hash,
                        userId: $row->user_id,
                        action: (string) $row->action,
                        entityType: $row->entity_type,
                        entityId: $row->entity_id,
                        details: is_array($row->details) ? $row->details : [],
                        createdAt: $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : ''
                    );

                    if (! hash_equals((string) $row->entry_hash, $recomputed)) {
                        $firstBreak = "Audit log #{$row->id}: entry_hash mismatch (tampering detected).";

                        return false;
                    }

                    $expectedPrev = (string) $row->entry_hash;
                }

                return true;
            });

        if ($firstBreak !== null) {
            $this->error($firstBreak);

            return self::FAILURE;
        }

        $this->info("Audit hash chain verified cleanly across {$checked} hashed entries.");

        return self::SUCCESS;
    }
}
