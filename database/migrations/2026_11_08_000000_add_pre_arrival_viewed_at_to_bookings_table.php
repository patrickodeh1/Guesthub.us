<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'pre_arrival_viewed_at')) {
                return;
            }
            $table->timestamp('pre_arrival_viewed_at')->nullable()->after('checkin_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'pre_arrival_viewed_at')) {
                return;
            }
            $table->dropColumn('pre_arrival_viewed_at');
        });
    }
};