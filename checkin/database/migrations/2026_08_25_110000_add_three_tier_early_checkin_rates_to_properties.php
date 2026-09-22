<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Three explicit time-window rates, replacing the old two-tier
            // 8am/12pm split. Ranges per client: 8am-12pm, 12pm-2pm,
            // 2pm-4pm — each its own flat charge, different per property.
            $table->decimal('early_checkin_rate_8am_12pm', 8, 2)->nullable()->after('early_checkin_rate_12pm');
            $table->decimal('early_checkin_rate_12pm_2pm', 8, 2)->nullable()->after('early_checkin_rate_8am_12pm');
            $table->decimal('early_checkin_rate_2pm_4pm', 8, 2)->nullable()->after('early_checkin_rate_12pm_2pm');
        });

        // Carry forward any existing data: old '8am' tier maps to the new
        // 8am-12pm window, old '12pm' tier doesn't map cleanly to a single
        // new window (it was a single point-in-time tier, the new model is
        // range-based) so it's left for admin to re-enter under whichever
        // window actually applies at each property.
        DB::table('properties')->whereNotNull('early_checkin_rate_8am')->update([
            'early_checkin_rate_8am_12pm' => DB::raw('early_checkin_rate_8am'),
        ]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['early_checkin_rate_8am_12pm', 'early_checkin_rate_12pm_2pm', 'early_checkin_rate_2pm_4pm']);
        });
    }
};
