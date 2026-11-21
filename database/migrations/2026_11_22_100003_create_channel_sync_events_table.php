<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_sync_events')) {
            return;
        }

        // Audit trail. Summaries only: never store API keys, tokens or full listing content.
        Schema::create('channel_sync_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_channel_link_id')->nullable()->constrained('listing_channel_links')->nullOnDelete();
            $table->unsignedBigInteger('listing_id')->nullable()->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('channel', 32)->nullable();
            $table->string('action', 48);
            $table->string('status', 16);           // ok | error | blocked | skipped
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sync_events');
    }
};
