<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_connections')) {
            return;
        }

        // One row per host-owned OTA account connected through Repull's hosted Connect flow.
        // The Repull API key is workspace-wide, so THIS table is what keeps hosts apart.
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32)->default('repull');
            $table->string('channel', 32);                 // airbnb | booking | vrbo
            $table->string('external_account_id', 64);     // provider's own id; ALWAYS a string (19-digit Airbnb ids)
            $table->string('access_type', 24)->nullable(); // full_access | messaging | read_only
            $table->string('status', 24)->default('connected'); // connected | needs_reauth | revoked | error
            $table->string('display_name')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'channel', 'external_account_id'], 'channel_connections_account_unique');
            $table->index(['owner_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
