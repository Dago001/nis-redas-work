<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('archive_documents')) {
            return;
        }

        Schema::create('archive_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('doc_type', 50);
            $table->text('description')->nullable();
            $table->string('report_period', 7)->nullable();
            $table->string('category', 20)->nullable();
            $table->string('scope_code', 50)->nullable();
            $table->string('zonal_code', 50)->nullable();
            $table->string('original_name', 255);
            $table->string('stored_path', 191)->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('scan_status', 20)->default('skipped');
            $table->date('retention_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope_code', 'report_period'], 'archive_docs_scope_period_idx');
            $table->index(['user_id', 'created_at'], 'archive_docs_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_documents');
    }
};
