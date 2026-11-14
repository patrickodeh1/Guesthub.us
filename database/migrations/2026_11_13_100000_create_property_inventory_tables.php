<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'name']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('default_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('name', 160);
            $table->string('sku', 80)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('category', 30)->default('other');
            $table->string('unit_of_measure', 40)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('model_number', 120)->nullable();
            $table->string('serial_number', 160)->nullable();
            $table->string('supplier', 160)->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->string('photo_path')->nullable();
            $table->date('last_ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['property_id', 'category']);
            $table->index(['property_id', 'barcode']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['inventory_item_id', 'inventory_location_id'], 'inventory_item_location_unique');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('cleaning_session_id')->nullable()->index();
            $table->string('movement_type', 20);
            $table->decimal('quantity', 12, 3);
            $table->string('reference', 160)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
            $table->index(['property_id', 'movement_type', 'occurred_at'], 'inventory_movement_report_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
    }
};
