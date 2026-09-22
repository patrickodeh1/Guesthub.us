<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('access_blocked_at')->nullable()->after('guest_authenticated_at');
            $table->text('access_blocked_reason')->nullable()->after('access_blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['access_blocked_at', 'access_blocked_reason']);
        });
    }
};
