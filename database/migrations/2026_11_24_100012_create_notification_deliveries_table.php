<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_deliveries')) {
            return;
        }

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->default('email');
            $table->string('template_key', 100);
            $table->string('status', 20)->default('pending'); // pending, processing, accepted, failed, skipped
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider', 40)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->json('provider_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'notification_deliveries_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
