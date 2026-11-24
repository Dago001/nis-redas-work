<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class PruneAuditLogCommand extends Command
{
    protected $signature = 'redas:audit:prune {--days= : Override retention period in days} {--force : Actually delete eligible records}';

    protected $description = 'Prune audit_logs entries older than the configured retention period';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? max(90, (int) $this->option('days'))
            : (int) config('audit.retention_days', 2555);

        $cutoff = now()->subDays($days);
        $count = AuditLog::query()->where('created_at', '<', $cutoff)->count();

        if (! $this->option('force')) {
            $this->info("Dry run: {$count} audit log entries older than {$days} days ({$cutoff->toDateString()}) would be pruned. Pass --force to execute.");

            return self::SUCCESS;
        }

        AuditLog::$allowPrune = true;
        try {
            $deleted = 0;
            AuditLog::query()->where('created_at', '<', $cutoff)->chunkById(500, function ($rows) use (&$deleted): void {
                foreach ($rows as $row) {
                    $row->delete();
                    $deleted++;
                }
            });
        } finally {
            AuditLog::$allowPrune = false;
        }

        AuditLogger::log(
            user: null,
            action: 'audit.pruned',
            status: 'success',
            entityType: 'audit_log',
            details: ['retention_days' => $days, 'cutoff' => $cutoff->toDateString(), 'deleted_count' => $deleted]
        );

        $this->info("Pruned {$deleted} audit log entries older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }
}
