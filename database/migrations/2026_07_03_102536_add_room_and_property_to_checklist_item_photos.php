<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('checklist_item_photos', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_item_photos', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn(['room_id', 'property_id']);
        });
    }
};
