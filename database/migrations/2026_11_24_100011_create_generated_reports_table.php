<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('generated_reports')) {
            return;
        }

        Schema::create('generated_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 60);
            $table->string('format', 10);
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('queued'); // queued, processing, completed, failed
            $table->string('stored_path', 191)->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'generated_reports_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
