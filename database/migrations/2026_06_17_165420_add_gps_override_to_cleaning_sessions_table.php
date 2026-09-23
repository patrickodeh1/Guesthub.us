<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->boolean('gps_override_enabled')->default(false);
            $table->foreignId('gps_override_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gps_override_reason')->nullable();
            $table->timestamp('gps_override_timestamp')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->dropForeign(['gps_override_approved_by']);
            $table->dropColumn([
                'gps_override_enabled',
                'gps_override_approved_by',
                'gps_override_reason',
                'gps_override_timestamp'
            ]);
        });
    }
};
