<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auth_challenges')) {
            return;
        }

        Schema::create('auth_challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 30)->default('login');
            $table->string('otp_hash', 64);
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->unsignedTinyInteger('sends')->default(1);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose'], 'auth_challenges_user_purpose_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_challenges');
    }
};
