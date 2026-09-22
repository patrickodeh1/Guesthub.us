<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Nullable, no DB-level default on purpose: existing property rows
            // stay NULL. The '16:00' standard is applied in code only
            // (Booking::effectiveCheckinTime()), so nothing is silently written
            // to existing data and nothing can "look configured" when it wasn't.
            $table->string('checkin_time')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('checkin_time');
        });
    }
};
