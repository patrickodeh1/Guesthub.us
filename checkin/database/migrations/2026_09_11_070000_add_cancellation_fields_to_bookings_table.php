<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest-initiated cancellations (arriving from the channel manager):
 * - cancelled_by_guest marks the cancellation as the guest's, so the status
 *   reads "Cancelled by Guest".
 * - cancellation_fee_applies is true when the cancellation landed inside the
 *   30-day window before arrival, so we're owed money: such bookings are NOT
 *   archived and go read-only instead. Cancellations outside that window are
 *   archived as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('cancelled_by_guest')->default(false)->after('cancelled_at');
            $table->boolean('cancellation_fee_applies')->default(false)->after('cancelled_by_guest');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by_guest', 'cancellation_fee_applies']);
        });
    }
};
