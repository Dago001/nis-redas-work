<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('applications', 'report_type')) {
                $table->string('report_type', 20)->default('original')->after('category');
            }
            if (! Schema::hasColumn('applications', 'report_period')) {
                $table->string('report_period', 7)->nullable()->after('report_type');
            }
            if (! Schema::hasColumn('applications', 'section_code')) {
                $table->string('section_code', 50)->nullable()->after('scope_code');
            }
            if (! Schema::hasColumn('applications', 'current_version')) {
                $table->unsignedInteger('current_version')->default(0)->after('last_action_by');
            }
            if (! Schema::hasColumn('applications', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('current_version');
            }
            if (! Schema::hasColumn('applications', 'deleted_at')) {
                $table->softDeletes();
            }

            $table->index(['category', 'report_period', 'scope_code'], 'applications_cat_period_scope_idx');
            $table->index(['zonal_code', 'report_period'], 'applications_zonal_period_idx');
            $table->index(['status', 'created_at'], 'applications_status_created_idx');
            $table->index(['user_id', 'created_at'], 'applications_user_created_idx');
        });

        // Non-destructive chunked backfill of report_period from JSON return_data
        DB::table('applications')
            ->select(['id', 'return_data'])
            ->whereNull('report_period')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $decoded = is_string($row->return_data)
                        ? json_decode($row->return_data, true)
                        : (is_array($row->return_data) ? $row->return_data : null);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $period = $decoded['report_period'] ?? $decoded['period'] ?? null;
                    if (is_string($period) && preg_match('/^\d{4}-\d{2}$/', trim($period))) {
                        DB::table('applications')
                            ->where('id', $row->id)
                            ->update(['report_period' => trim($period)]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('applications')) {
            return;
        }

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex('applications_cat_period_scope_idx');
            $table->dropIndex('applications_zonal_period_idx');
            $table->dropIndex('applications_status_created_idx');
            $table->dropIndex('applications_user_created_idx');

            if (Schema::hasColumn('applications', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
            foreach (['deleted_at', 'current_version', 'section_code', 'report_period', 'report_type'] as $col) {
                if (Schema::hasColumn('applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
