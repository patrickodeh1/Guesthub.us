<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->string('guest_name')->nullable()->after('name');
            $t->string('internal_name')->nullable()->after('guest_name');
        });
        DB::table('properties')->update(['guest_name' => DB::raw('name'), 'internal_name' => DB::raw('name')]);

        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('last_registration_reminder_at')->nullable();
            $t->timestamp('arrival_alert_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->dropColumn(['guest_name', 'internal_name']));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['last_registration_reminder_at', 'arrival_alert_sent_at']));
    }
};
