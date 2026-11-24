<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportVersionBackfill
{
    /**
     * Create version 1 snapshots for any historical applications lacking a report_versions row.
     * Safe and idempotent to run repeatedly.
     */
    public static function run(): int
    {
        if (! Schema::hasTable('applications') || ! Schema::hasTable('report_versions')) {
            return 0;
        }

        $created = 0;

        DB::table('applications')
            ->select(['id', 'user_id', 'status', 'return_data', 'current_version', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$created): void {
                foreach ($rows as $row) {
                    $exists = DB::table('report_versions')
                        ->where('application_id', $row->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $json = is_string($row->return_data)
                        ? $row->return_data
                        : json_encode($row->return_data ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    DB::table('report_versions')->insert([
                        'application_id' => $row->id,
                        'version_no' => 1,
                        'return_data' => $json,
                        'data_hash' => hash('sha256', (string) $json),
                        'status_at_snapshot' => (string) ($row->status ?? 'pending'),
                        'reason' => 'backfill',
                        'created_by' => $row->user_id,
                        'created_at' => $row->created_at ?? now(),
                    ]);

                    DB::table('applications')
                        ->where('id', $row->id)
                        ->update(['current_version' => max(1, (int) ($row->current_version ?? 0))]);

                    $created++;
                }
            });

        return $created;
    }
}
