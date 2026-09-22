<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // 'manual' (default, matches every existing booking) or
            // 'channex'. Nothing reads/branches on this for existing rows
            // since they'll all just be 'manual'.
            $table->string('source')->default('manual')->after('reservation_id');

            // The real OTA reservation id as reported by Channex, separate
            // from the existing free-text `reservation_id` field (which
            // stays exactly as-is / admin-typed for manual bookings).
            $table->string('channex_booking_id')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['source', 'channex_booking_id']);
        });
    }
};
