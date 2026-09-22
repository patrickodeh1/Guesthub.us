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
            $table->decimal('late_checkout_rate_authorized_per_30min', 8, 2)->nullable()->after('late_checkout_rate_authorized_hourly');
            $table->decimal('late_checkout_rate_unauthorized_per_30min', 8, 2)->nullable()->after('late_checkout_rate_unauthorized_hourly');
        });

        // Carry forward existing hourly rates as a reasonable starting
        // point (hourly / 2 = a rough per-half-hour equivalent) — admin
        // should review these, since the old figures were never meant to
        // be divided this way, but this avoids silently zeroing out every
        // property's existing rate on deploy.
        DB::table('properties')->whereNotNull('late_checkout_rate_authorized_hourly')->update([
            'late_checkout_rate_authorized_per_30min' => DB::raw('late_checkout_rate_authorized_hourly / 2'),
        ]);
        DB::table('properties')->whereNotNull('late_checkout_rate_unauthorized_hourly')->update([
            'late_checkout_rate_unauthorized_per_30min' => DB::raw('late_checkout_rate_unauthorized_hourly / 2'),
        ]);
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['late_checkout_rate_authorized_per_30min', 'late_checkout_rate_unauthorized_per_30min']);
        });
    }
};
