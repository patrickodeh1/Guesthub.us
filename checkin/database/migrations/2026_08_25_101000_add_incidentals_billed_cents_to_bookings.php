<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Snapshot of how much of incidentals_charge has already been
            // captured — either as part of the combined pre-checkin charge
            // (parking + incidentals + early check-in) or a later standalone
            // incidentals charge. The post-checkout incidentals card only
            // bills the difference between the current incidentals_charge
            // and this value, so increasing incidentals_charge after the
            // combined charge already ran doesn't re-bill what was already
            // paid.
            $table->unsignedInteger('incidentals_billed_cents')->default(0)->after('incidentals_charge');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('incidentals_billed_cents');
        });
    }
};
