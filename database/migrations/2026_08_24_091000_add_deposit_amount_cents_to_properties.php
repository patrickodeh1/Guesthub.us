<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Flat-dollar ceiling on the pre-checkin combined charge
            // (parking + incidentals, capped here, then a global % fee
            // added on top — see Booking::calculatePreCheckinChargeCents()).
            // Nullable — falls back to the global default_deposit_cap_cents
            // setting when unset. Existing properties are unaffected until
            // an admin sets one explicitly.
            $table->unsignedInteger('deposit_cap_cents')->nullable()->after('channex_property_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('deposit_cap_cents');
        });
    }
};
