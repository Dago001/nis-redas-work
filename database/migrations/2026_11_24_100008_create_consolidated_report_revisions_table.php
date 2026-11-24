<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consolidated_report_revisions')) {
            return;
        }

        Schema::create('consolidated_report_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consolidated_report_id')->constrained('consolidated_reports')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->longText('narrative')->nullable();
            $table->json('aggregated_data')->nullable();
            $table->json('gaps')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('change_note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['consolidated_report_id', 'revision_no'], 'consolidated_revisions_report_rev_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_report_revisions');
    }
};
