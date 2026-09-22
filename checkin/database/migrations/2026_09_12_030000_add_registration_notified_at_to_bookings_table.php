<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Set once the "registration completed" alert has gone out, so a
            // rejected/re-uploaded ID never re-triggers it. Rejections reset
            // the booking status to pending, which previously made every
            // re-upload look like a brand-new registration.
            $table->timestamp('registration_notified_at')->nullable()->after('identity_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('registration_notified_at');
        });
    }
};
