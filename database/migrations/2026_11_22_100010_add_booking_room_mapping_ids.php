<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_rooms', 'booking_room_id')) {
                $table->string('booking_room_id', 80)->nullable();
            }
            if (! Schema::hasColumn('listing_rooms', 'booking_rate_id')) {
                $table->string('booking_rate_id', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_rooms', function (Blueprint $table) {
            foreach (['booking_room_id', 'booking_rate_id'] as $column) {
                if (Schema::hasColumn('listing_rooms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
