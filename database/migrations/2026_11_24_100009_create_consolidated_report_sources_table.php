<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consolidated_report_sources')) {
            return;
        }

        Schema::create('consolidated_report_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('consolidated_report_revisions')->restrictOnDelete();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('report_version_id')->constrained('report_versions')->restrictOnDelete();
            $table->boolean('included')->default(true);
            $table->string('exclusion_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['revision_id', 'application_id'], 'consolidated_sources_rev_app_unique');
            $table->index('application_id', 'consolidated_sources_app_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_report_sources');
    }
};
