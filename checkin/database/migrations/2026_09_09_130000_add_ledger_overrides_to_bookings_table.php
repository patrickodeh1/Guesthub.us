<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable per-guest ledger (task: "For editing the guest amount I should
 * see the breakdowns and then be able to adjust each amount (parking,
 * early check in, late check out, incidentals hold, etc)"):
 *
 * - early_checkin_charge_override / late_checkout_charge_override: admin can
 *   override the property/tier-derived rate on a per-booking basis, same
 *   pattern as the existing parking_charge_override. Both null by default
 *   so the computed rate is used until an admin explicitly overrides it.
 * - ledger_published_at: gates guest visibility. Admin can freely edit the
 *   breakdown; the guest continues to see only the last-published total
 *   until admin explicitly marks the ledger ready to show again ("this will
 *   be shown to the guest when ready").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('early_checkin_charge_override', 8, 2)->nullable()->after('early_checkin_tier');
            $table->decimal('late_checkout_charge_override', 8, 2)->nullable()->after('late_checkout_hours');
            $table->timestamp('ledger_published_at')->nullable()->after('incidentals_charge');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['early_checkin_charge_override', 'late_checkout_charge_override', 'ledger_published_at']);
        });
    }
};
