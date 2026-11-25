<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'surname')) {
                $table->string('surname', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('surname');
            }
            if (! Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name', 100)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('users', 'rank')) {
                $table->string('rank', 80)->nullable()->after('service_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['rank', 'middle_name', 'first_name', 'surname'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
