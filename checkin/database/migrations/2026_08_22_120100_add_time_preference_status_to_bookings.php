<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // pending | approved | denied. NULL = no non-standard time was
            // ever requested (either no preference set, or it matched the
            // property standard) — nothing to review.
            $table->string('checkin_time_status')->nullable()->after('checkin_time_preference');
            $table->string('checkout_time_status')->nullable()->after('checkout_time_preference');
        });

        // Backfill only the new status columns. Any booking that already has
        // a preference saved was, before this migration, having that
        // preference honored immediately with no approval step. To avoid
        // changing behavior for any existing/currently-staying guest on
        // deploy, mark those as already approved. This does not touch
        // checkin_time_preference / checkout_time_preference or any
        // property data — only the brand new status columns.
        DB::table('bookings')
            ->whereNotNull('checkin_time_preference')
            ->update(['checkin_time_status' => 'approved']);

        DB::table('bookings')
            ->whereNotNull('checkout_time_preference')
            ->update(['checkout_time_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['checkin_time_status', 'checkout_time_status']);
        });
    }
};
