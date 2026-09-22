<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set once the "check-out is available tomorrow" alert has been sent, so the
 * reminder only ever goes out one time per booking. Nullable so existing
 * bookings are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checkout_reminder_sent_at')->nullable()->after('checkin_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checkout_reminder_sent_at');
        });
    }
};
