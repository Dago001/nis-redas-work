<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('refresh_tokens')) {
            return;
        }

        Schema::table('refresh_tokens', function (Blueprint $table): void {
            if (! Schema::hasColumn('refresh_tokens', 'family_id')) {
                $table->string('family_id', 64)->nullable()->after('jti')->index();
            }

            if (! Schema::hasColumn('refresh_tokens', 'replaced_by_jti')) {
                $table->string('replaced_by_jti', 64)->nullable()->after('revoked_at');
            }

            if (! Schema::hasColumn('refresh_tokens', 'reuse_detected_at')) {
                $table->timestamp('reuse_detected_at')->nullable()->after('replaced_by_jti');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('refresh_tokens')) {
            return;
        }

        Schema::table('refresh_tokens', function (Blueprint $table): void {
            if (Schema::hasColumn('refresh_tokens', 'reuse_detected_at')) {
                $table->dropColumn('reuse_detected_at');
            }
            if (Schema::hasColumn('refresh_tokens', 'replaced_by_jti')) {
                $table->dropColumn('replaced_by_jti');
            }
            if (Schema::hasColumn('refresh_tokens', 'family_id')) {
                $table->dropIndex(['family_id']);
                $table->dropColumn('family_id');
            }
        });
    }
};
