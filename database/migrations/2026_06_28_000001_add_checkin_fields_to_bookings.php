<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('early_checkin')->default(false)->after('parking_needed');
            $table->string('checkin_time_preference')->nullable()->after('early_checkin');
        });
    }
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['early_checkin', 'checkin_time_preference']);
        });
    }
};
