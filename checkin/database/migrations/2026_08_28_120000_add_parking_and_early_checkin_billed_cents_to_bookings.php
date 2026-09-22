<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Same idea as incidentals_billed_cents: snapshot of how much of
            // the parking / early check-in charge has already been captured
            // — either as part of the combined pre-checkin charge (parking +
            // incidentals + early check-in) or a later standalone charge.
            // The standalone "pay now" cards only bill the difference
            // between the current charge and this value, so a guest who
            // already paid parking/early check-in via the combined deposit
            // is never charged for it again.
            $table->unsignedInteger('parking_billed_cents')->default(0)->after('incidentals_billed_cents');
            $table->unsignedInteger('early_checkin_billed_cents')->default(0)->after('parking_billed_cents');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['parking_billed_cents', 'early_checkin_billed_cents']);
        });
    }
};
