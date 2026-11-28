<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'geofence_lat')) {
                $table->decimal('geofence_lat', 10, 7)->nullable()->after('geolocation_enabled');
            }
            if (! Schema::hasColumn('users', 'geofence_lng')) {
                $table->decimal('geofence_lng', 10, 7)->nullable()->after('geofence_lat');
            }
            if (! Schema::hasColumn('users', 'geofence_radius_km')) {
                $table->unsignedInteger('geofence_radius_km')->nullable()->default(25)->after('geofence_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['geofence_radius_km', 'geofence_lng', 'geofence_lat'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
