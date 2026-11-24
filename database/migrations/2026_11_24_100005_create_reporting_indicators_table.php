<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reporting_indicators')) {
            return;
        }

        Schema::create('reporting_indicators', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 191)->unique();
            $table->string('name', 191);
            $table->text('definition')->nullable();
            $table->string('data_type', 20)->default('integer'); // integer, decimal
            $table->string('unit', 50)->nullable();
            $table->string('category', 20)->nullable();
            $table->string('section', 100)->nullable();
            $table->json('validation_rules')->nullable();
            $table->string('status', 20)->default('provisional'); // provisional, approved, excluded
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_indicators');
    }
};
