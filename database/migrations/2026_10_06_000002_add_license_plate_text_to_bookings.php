<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'license_plate')) {
                $table->string('license_plate', 12)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'license_plate_state')) {
                $table->string('license_plate_state', 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['license_plate', 'license_plate_state']);
        });
    }
};
