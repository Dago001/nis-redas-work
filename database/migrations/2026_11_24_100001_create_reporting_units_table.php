<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reporting_units')) {
            return;
        }

        Schema::create('reporting_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 191);
            $table->string('type', 20); // zone, formation, directorate, section, cgis_unit, headquarters
            $table->string('formation_type', 30)->nullable(); // state_command, airport_command, border_formation, other
            $table->foreignId('parent_id')->nullable()->constrained('reporting_units')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'parent_id'], 'reporting_units_type_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_units');
    }
};
