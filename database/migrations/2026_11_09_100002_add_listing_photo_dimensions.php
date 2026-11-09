<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listing_photos')) {
            Schema::table('listing_photos', function (Blueprint $table) {
                if (! Schema::hasColumn('listing_photos', 'width')) {
                    $table->unsignedSmallInteger('width')->nullable();
                }
                if (! Schema::hasColumn('listing_photos', 'height')) {
                    $table->unsignedSmallInteger('height')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('listing_photos')) {
            foreach (['height', 'width'] as $column) {
                if (Schema::hasColumn('listing_photos', $column)) {
                    Schema::table('listing_photos', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
