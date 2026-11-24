<?php

use App\Services\Reporting\ReportVersionBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_versions')) {
            Schema::create('report_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
                $table->unsignedInteger('version_no');
                $table->json('return_data')->nullable();
                $table->string('data_hash', 64);
                $table->string('status_at_snapshot', 40);
                $table->string('reason', 30)->default('submitted'); // submitted, resubmitted, backfill
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['application_id', 'version_no'], 'report_versions_app_ver_unique');
            });
        }

        if (class_exists(ReportVersionBackfill::class)) {
            ReportVersionBackfill::run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_versions');
    }
};
