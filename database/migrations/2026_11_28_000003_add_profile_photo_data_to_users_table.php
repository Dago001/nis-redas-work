<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'profile_photo_data')) {
                $table->longText('profile_photo_data')->nullable()->after('profile_photo_path');
            }
            if (! Schema::hasColumn('users', 'profile_photo_mime')) {
                $table->string('profile_photo_mime', 64)->nullable()->after('profile_photo_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['profile_photo_data', 'profile_photo_mime'],
                static fn (string $c) => Schema::hasColumn('users', $c)
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
