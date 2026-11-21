<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            if (! Schema::hasColumn('amenities', 'repull_amenity_id')) {
                $table->string('repull_amenity_id', 100)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            if (Schema::hasColumn('amenities', 'repull_amenity_id')) {
                $table->dropIndex(['repull_amenity_id']);
                $table->dropColumn('repull_amenity_id');
            }
        });
    }
};
