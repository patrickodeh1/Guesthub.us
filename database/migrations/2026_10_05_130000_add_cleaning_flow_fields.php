<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $t) {
            $t->unsignedBigInteger('housekeeper_id')->nullable()->change();
            $t->unsignedBigInteger('booking_id')->nullable()->index();
            $t->boolean('no_guest')->default(false);
            $t->string('assignment_status')->default('unassigned'); // unassigned | pending_confirmation | confirmed
            $t->timestamp('cleaner_notified_at')->nullable();
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('unit_ready_sent_at')->nullable();
        });
        // Sessions that already have a cleaner are treated as confirmed.
        \Illuminate\Support\Facades\DB::table('cleaning_sessions')->whereNotNull('housekeeper_id')->update(['assignment_status' => 'confirmed']);
    }

    public function down(): void
    {
        Schema::table('cleaning_sessions', fn (Blueprint $t) => $t->dropColumn(['booking_id', 'no_guest', 'assignment_status', 'cleaner_notified_at']));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('unit_ready_sent_at'));
    }
};
