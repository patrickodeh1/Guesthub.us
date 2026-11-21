<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_rooms', 'beds')) {
                $table->json('beds')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('listing_rooms', 'beds')) {
                $table->dropColumn('beds');
            }
        });
    }
};
