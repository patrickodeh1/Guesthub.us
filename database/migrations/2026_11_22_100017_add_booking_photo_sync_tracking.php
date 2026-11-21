<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_photos', 'booking_listing_id')) {
                $table->string('booking_listing_id', 64)->nullable();
            }
            if (! Schema::hasColumn('listing_photos', 'booking_uploaded_at')) {
                $table->timestamp('booking_uploaded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('listing_photos', function (Blueprint $table) {
            foreach (['booking_listing_id', 'booking_uploaded_at'] as $column) {
                if (Schema::hasColumn('listing_photos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
