<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The OTA/booking platform a reservation came from (Airbnb, Vrbo,
 * Booking.com, etc.), populated from the channel manager's ota_name on
 * import and editable by hand for manually-entered bookings. Nullable so
 * every already-created booking is untouched; the guest payment flow falls
 * back to its previous wording when it's empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_platform')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('booking_platform');
        });
    }
};
