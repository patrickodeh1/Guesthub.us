<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            // 'popup' shows a modal; 'step' is injected into the check-in or
            // check-out wizard as its own step.
            $table->string('type')->default('popup');
            // Which part of the portal this applies to.
            $table->string('phase')->default('checkin'); // checkin|checkout|guide|any
            // Which day it can appear on.
            $table->string('day_scope')->default('any'); // any|arrival_day|checkout_day
            // Optional local-time window (handles windows that wrap midnight).
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            // Optional parking condition: true = only parkers, false = only
            // non-parkers, null = anyone.
            $table->boolean('requires_parking')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('once_per_booking')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Default notice requested by the client: guests arriving at 11pm or
        // later are told the smart lights won't be at their usual brightness.
        // Scoped to all properties (property_id null = every property) and
        // editable/deactivatable from Admin > Guest Notices.
        DB::table('guest_notices')->insert([
            'property_id' => null,
            'title' => 'Welcome in — a note about the lights',
            'body' => 'Because you are arriving late, the smart lights in your unit are set to a dim, nighttime brightness so they do not wake anyone. Use the light switches or the panel to brighten them as much as you like.',
            'type' => 'popup',
            'phase' => 'checkin',
            'day_scope' => 'arrival_day',
            'start_time' => '23:00:00',
            'end_time' => '04:00:00',
            'requires_parking' => null,
            'active' => true,
            'once_per_booking' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_notices');
    }
};
