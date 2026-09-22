<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host's manual "the unit is ready and the guest may check in" approval.
 * Until this is set, a fully pre-checked-in guest is held on the "your unit
 * isn't quite ready yet" screen instead of seeing the address/arrival steps.
 * Nullable so existing bookings are untouched (they simply aren't gated until
 * a host approves them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checkin_approved_at')->nullable()->after('deposit_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checkin_approved_at');
        });
    }
};
