<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Whether the guest is asked for a license-plate photo when they
            // say they need parking. Defaults to required (true) for every
            // existing and new property; an admin can uncheck it per
            // property (e.g. properties with no on-site parking to track).
            $table->boolean('requires_vehicle_photo')->default(true)->after('parking_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('requires_vehicle_photo');
        });
    }
};
