<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listing_channel_links')) {
            return;
        }

        // One row per listing x channel. States:
        // not_connected | publishing | published (content landed, not live) | live | partial | error | unlisted
        Schema::create('listing_channel_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('property_listings')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->foreignId('connection_id')->nullable()->constrained('channel_connections')->nullOnDelete();
            $table->string('external_listing_id', 64)->nullable(); // Booking.com property id / Airbnb listing id (string)
            $table->string('state', 24)->default('not_connected');
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->json('sections')->nullable();      // per-section publish result (partial success is normal)
            $table->json('locked_fields')->nullable(); // fields the channel refuses to change (200 but not applied)
            $table->json('warnings')->nullable();
            $table->string('remote_url')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_channel_links');
    }
};
