<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_indicator_values')) {
            return;
        }

        Schema::create('report_indicator_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('report_version_id')->constrained('report_versions')->restrictOnDelete();
            $table->foreignId('indicator_id')->constrained('reporting_indicators')->restrictOnDelete();
            $table->string('reporting_unit_code', 50)->nullable();
            $table->string('category', 20);
            $table->string('report_period', 7)->nullable();
            $table->decimal('value', 20, 4);
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->unique(['report_version_id', 'indicator_id'], 'riv_version_indicator_unique');
            $table->index(['indicator_id', 'report_period', 'is_current'], 'riv_indicator_period_current_idx');
            $table->index(['report_period', 'reporting_unit_code'], 'riv_period_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_indicator_values');
    }
};
