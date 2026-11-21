<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repull_amenity_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('canonical_id', 100)->unique();
            $table->string('category', 80)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // These are examples explicitly used in Repull's own canonical-content
        // documentation. The catalog is admin-maintained; do not guess other IDs.
        $now = now();
        DB::table('repull_amenity_catalog')->insert([
            ['label' => 'Wi-Fi', 'canonical_id' => 'wifi', 'category' => 'Connectivity', 'active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Kitchen', 'canonical_id' => 'kitchen', 'category' => 'Kitchen', 'active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'Free parking', 'canonical_id' => 'free_parking', 'category' => 'Parking', 'active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('repull_amenity_catalog');
    }
};
