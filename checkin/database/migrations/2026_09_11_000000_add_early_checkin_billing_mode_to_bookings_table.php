<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-booking decision for how early check-in is settled: billed to the
 * guest upfront as part of their pre-check-in charge ("charge"), or
 * deducted from their incidentals hold at checkout like late checkout
 * ("deduct_from_hold"). Defaults to "charge" (null == charge) so existing
 * bookings behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('early_checkin_billing_mode')->nullable()->after('early_checkin_charge_override');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('early_checkin_billing_mode');
        });
    }
};
