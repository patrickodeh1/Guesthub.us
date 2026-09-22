<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('checkout_time')->default('11:00')->after('timezone');
        });
    }
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('checkout_time');
        });
    }
};
