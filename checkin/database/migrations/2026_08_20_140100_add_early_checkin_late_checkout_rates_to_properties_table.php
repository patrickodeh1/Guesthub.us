<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('early_checkin_rate_8am', 8, 2)->nullable()->after('parking_rate_saturday');
            $table->decimal('early_checkin_rate_12pm', 8, 2)->nullable()->after('early_checkin_rate_8am');
            $table->decimal('late_checkout_rate_authorized_hourly', 8, 2)->nullable()->after('early_checkin_rate_12pm');
            $table->decimal('late_checkout_rate_unauthorized_hourly', 8, 2)->nullable()->after('late_checkout_rate_authorized_hourly');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'early_checkin_rate_8am', 'early_checkin_rate_12pm',
                'late_checkout_rate_authorized_hourly', 'late_checkout_rate_unauthorized_hourly',
            ]);
        });
    }
};
