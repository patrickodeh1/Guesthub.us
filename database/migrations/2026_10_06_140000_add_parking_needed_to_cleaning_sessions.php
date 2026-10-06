<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->boolean('parking_needed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $table) {
            $table->dropColumn('parking_needed');
        });
    }
};
