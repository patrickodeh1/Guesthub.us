<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_session_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cleaning_session_id')->constrained('cleaning_sessions')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('guest_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity_available', 12, 3);
            $table->decimal('quantity_taken', 12, 3)->default(0);
            $table->decimal('quantity_after_restock', 12, 3);
            $table->decimal('quantity_guest_consumed', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cleaning_session_id', 'inventory_item_id'], 'inventory_session_item_unique');
            $table->index(['property_id', 'recorded_by', 'created_at'], 'inventory_session_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_session_records');
    }
};
