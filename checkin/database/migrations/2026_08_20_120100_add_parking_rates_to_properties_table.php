<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('parking_rate_sunday', 8, 2)->nullable()->after('parking_instructions');
            $table->decimal('parking_rate_monday', 8, 2)->nullable()->after('parking_rate_sunday');
            $table->decimal('parking_rate_tuesday', 8, 2)->nullable()->after('parking_rate_monday');
            $table->decimal('parking_rate_wednesday', 8, 2)->nullable()->after('parking_rate_tuesday');
            $table->decimal('parking_rate_thursday', 8, 2)->nullable()->after('parking_rate_wednesday');
            $table->decimal('parking_rate_friday', 8, 2)->nullable()->after('parking_rate_thursday');
            $table->decimal('parking_rate_saturday', 8, 2)->nullable()->after('parking_rate_friday');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'parking_rate_sunday', 'parking_rate_monday', 'parking_rate_tuesday',
                'parking_rate_wednesday', 'parking_rate_thursday', 'parking_rate_friday',
                'parking_rate_saturday',
            ]);
        });
    }
};
