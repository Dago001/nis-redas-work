<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'scope_code')) {
                $table->string('scope_code', 100)->nullable()->after('entity_id');
            }
            if (! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 64)->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('audit_logs', 'http_method')) {
                $table->string('http_method', 10)->nullable()->after('request_id');
            }
            if (! Schema::hasColumn('audit_logs', 'route_name')) {
                $table->string('route_name', 150)->nullable()->after('http_method');
            }
            if (! Schema::hasColumn('audit_logs', 'prev_hash')) {
                $table->string('prev_hash', 64)->nullable()->after('status');
            }
            if (! Schema::hasColumn('audit_logs', 'entry_hash')) {
                $table->string('entry_hash', 64)->nullable()->after('prev_hash')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('audit_logs', 'entry_hash')) {
                $table->dropIndex(['entry_hash']);
                $table->dropColumn('entry_hash');
            }
            foreach (['prev_hash', 'route_name', 'http_method', 'request_id', 'scope_code'] as $col) {
                if (Schema::hasColumn('audit_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
