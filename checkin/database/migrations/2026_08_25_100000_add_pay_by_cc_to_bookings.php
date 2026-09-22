<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Admin-set per booking (booking create/edit form), not
            // guest-facing — admin already knows from working the
            // reservation whether this guest wants to pay via our site or
            // through the OTA/platform directly. Defaults false: existing
            // bookings and any new booking admin hasn't explicitly opted in
            // show the "pay via platform" alt instructions, same as before
            // Stripe existed, until admin actively checks the box.
            $table->boolean('pay_by_cc')->default(false)->after('deposit_amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('pay_by_cc');
        });
    }
};
