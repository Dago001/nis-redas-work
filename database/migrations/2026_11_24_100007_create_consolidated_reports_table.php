<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consolidated_reports')) {
            return;
        }

        Schema::create('consolidated_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('level', 20); // zonal, directorate
            $table->string('scope_code', 50);
            $table->string('report_period', 7);
            $table->string('title', 191);
            $table->string('status', 20)->default('draft'); // draft, submitted, returned, approved
            $table->string('workflow_stage', 40)->default('draft');
            $table->unsignedInteger('current_revision_no')->default(0);
            $table->unsignedInteger('approved_revision_no')->nullable();
            $table->boolean('is_stale')->default(false);
            $table->text('stale_reason')->nullable();
            $table->timestamp('stale_flagged_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('returned_reason')->nullable();
            $table->timestamps();

            $table->unique(['level', 'scope_code', 'report_period'], 'consolidated_level_scope_period_unique');
            $table->index(['report_period', 'status'], 'consolidated_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_reports');
    }
};
