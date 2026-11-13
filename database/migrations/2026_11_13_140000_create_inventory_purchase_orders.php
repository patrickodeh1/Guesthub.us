<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier', 160);
            $table->string('reference', 160)->nullable();
            $table->string('status', 24)->default('ordered');
            $table->date('ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status', 'ordered_at'], 'inventory_po_property_status_idx');
        });

        Schema::create('inventory_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('inventory_purchase_orders')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchase_order_lines');
        Schema::dropIfExists('inventory_purchase_orders');
    }
};
