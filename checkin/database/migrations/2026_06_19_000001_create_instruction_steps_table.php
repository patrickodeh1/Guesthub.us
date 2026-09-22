<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruction_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['checkin', 'checkout', 'parking']);
            $table->integer('sort_order')->default(0);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['property_id', 'type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruction_steps');
    }
};
