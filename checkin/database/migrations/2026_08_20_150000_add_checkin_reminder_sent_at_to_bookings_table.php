<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Tracks whether the "time to check in" alert (task 30) has already
            // been sent for this booking, so the scheduled reminder command
            // doesn't send it more than once per booking.
            $table->timestamp('checkin_reminder_sent_at')->nullable()->after('incidentals_charge');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checkin_reminder_sent_at');
        });
    }
};
