<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('checkout_soon_sent_at')->nullable();
            $t->timestamp('checkout_overdue_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropColumn(['checkout_soon_sent_at', 'checkout_overdue_sent_at']);
        });
    }
};
