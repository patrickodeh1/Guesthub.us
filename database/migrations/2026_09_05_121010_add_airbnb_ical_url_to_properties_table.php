<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the client's Airbnb calendar export URL (from Airbnb ->
     * Calendar -> Availability settings -> Export calendar). Used only by
     * the manual "Import from Airbnb" button (AirbnbIcalImporter) -- never
     * polled automatically. The URL itself contains an opaque auth token
     * as a query param, so treat it with the same care as a credential
     * (avoid logging it in full).
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('airbnb_ical_url')->nullable()->after('channex_room_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('airbnb_ical_url');
        });
    }
};
