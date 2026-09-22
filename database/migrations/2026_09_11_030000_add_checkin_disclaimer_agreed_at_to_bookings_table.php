<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set when a guest has read and accepted the arrival-day disclaimer (the
 * "read your instructions before you travel" gate shown before the property
 * address is revealed). Nullable so every existing booking is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checkin_disclaimer_agreed_at')->nullable()->after('guest_authenticated_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checkin_disclaimer_agreed_at');
        });
    }
};
