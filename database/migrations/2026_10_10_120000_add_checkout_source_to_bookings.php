<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('checkout_source')->nullable()->after('checked_out_at');
            $table->timestamp('late_checkout_deducted_at')->nullable()->after('late_checkout_actual_time');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['checkout_source', 'late_checkout_deducted_at']);
        });
    }
};
