<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('property_listings', 'rooms_sync_enabled')) {
            Schema::table('property_listings', function (Blueprint $table) {
                $table->boolean('rooms_sync_enabled')->default(false);
            });
        }

        if (! Schema::hasTable('listing_rooms')) {
            Schema::create('listing_rooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_id')->constrained('property_listings')->cascadeOnDelete();
                $table->string('room_type', 40);
                $table->unsignedSmallInteger('room_number')->nullable();
                $table->string('room_name', 120)->nullable();
                $table->string('bed_type', 40)->nullable();
                $table->unsignedSmallInteger('bed_quantity')->nullable();
                $table->boolean('is_private')->default(true);
                $table->timestamps();
                $table->index(['listing_id', 'room_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_rooms');
        if (Schema::hasColumn('property_listings', 'rooms_sync_enabled')) {
            Schema::table('property_listings', fn (Blueprint $table) => $table->dropColumn('rooms_sync_enabled'));
        }
    }
};
