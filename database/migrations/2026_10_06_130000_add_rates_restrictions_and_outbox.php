<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_availabilities', function (Blueprint $table) {
            // NULL = "not set": never pushed to Channex.
            $table->decimal('rate', 10, 2)->nullable()->after('status');
            $table->unsignedSmallInteger('min_stay_arrival')->nullable()->after('rate');
            $table->unsignedSmallInteger('min_stay_through')->nullable()->after('min_stay_arrival');
            $table->unsignedSmallInteger('max_stay')->nullable()->after('min_stay_through');
            $table->boolean('stop_sell')->nullable()->after('max_stay');
            $table->boolean('closed_to_arrival')->nullable()->after('stop_sell');
            $table->boolean('closed_to_departure')->nullable()->after('closed_to_arrival');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('channex_rate_plan_id')->nullable()->after('channex_room_type_id');
            // guesthub = Guesthub pushes rates/restrictions; external = PriceLabs etc. own them.
            $table->string('rate_source', 10)->default('external')->after('channex_rate_plan_id');
        });

        Schema::create('channex_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12); // availability | restrictions
            $table->date('date');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'kind', 'date']);
            $table->index('next_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channex_outbox');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['channex_rate_plan_id', 'rate_source']);
        });

        Schema::table('property_availabilities', function (Blueprint $table) {
            $table->dropColumn(['rate', 'min_stay_arrival', 'min_stay_through', 'max_stay', 'stop_sell', 'closed_to_arrival', 'closed_to_departure']);
        });
    }
};
