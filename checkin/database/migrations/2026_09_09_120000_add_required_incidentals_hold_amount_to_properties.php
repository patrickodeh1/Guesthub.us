<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-property required incidentals hold amount: the flat amount the host
 * wants held against incidentals for every booking at that property, so
 * it doesn't have to be typed per-booking. Parking already has its own
 * per-weekday rate fields on properties, so no equivalent field is needed
 * here for parking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('required_incidentals_hold_amount', 8, 2)->nullable()->after('deposit_cap_cents');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('required_incidentals_hold_amount');
        });
    }
};
