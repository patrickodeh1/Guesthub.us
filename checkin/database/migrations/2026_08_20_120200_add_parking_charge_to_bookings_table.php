<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('parking_charge', 8, 2)->nullable()->after('parking_needed');
            $table->decimal('parking_charge_override', 8, 2)->nullable()->after('parking_charge');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['parking_charge', 'parking_charge_override']);
        });
    }
};
