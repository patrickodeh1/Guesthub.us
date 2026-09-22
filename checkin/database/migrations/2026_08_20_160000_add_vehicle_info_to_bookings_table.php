<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Task 34: when a guest opts into parking, collect their vehicle's
            // make/model and a photo of their license plate.
            $table->string('vehicle_make_model')->nullable()->after('checkin_reminder_sent_at');
            $table->string('license_plate_photo_path')->nullable()->after('vehicle_make_model');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['vehicle_make_model', 'license_plate_photo_path']);
        });
    }
};
