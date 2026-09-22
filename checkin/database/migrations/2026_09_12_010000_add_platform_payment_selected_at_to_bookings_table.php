<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Set when the guest chooses to pay the incidentals hold on their
            // booking platform (Airbnb/VRBO/etc.) instead of by card. Because
            // there is no webhook to confirm an off-platform payment, this
            // records the choice so the guest advances past the payment screen
            // into "pending approval" and never sees the amount again.
            $table->timestamp('platform_payment_selected_at')->nullable()->after('deposit_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('platform_payment_selected_at');
        });
    }
};
