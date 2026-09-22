<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // The holder name read off the ID, kept for admin visibility and
            // so we can prove why a name-mismatch auto-rejection happened.
            $table->string('id_name')->nullable()->after('id_number');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('id_name');
        });
    }
};
