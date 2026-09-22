<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guesthub's own ledger of availability/rate per date, per property --
     * the source of truth we push to Channex (which then pushes to Airbnb).
     * One row per property+date. `source` tracks where a given date's data
     * came from (manual admin edit, one-time iCal import of existing Airbnb
     * bookings, or a future dynamic pricing tool feed) purely for auditing/
     * debugging -- nothing branches on it today.
     */
    public function up(): void
    {
        Schema::create('property_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_available')->default(true);
            $table->string('source')->default('ical'); // ical is the only source now -- see PropertyAvailabilityController
            $table->timestamps();

            $table->unique(['property_id', 'date']);
            $table->index(['property_id', 'date', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_availabilities');
    }
};
