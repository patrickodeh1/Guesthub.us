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
        Schema::create('instructional_videos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_file_path');
            $table->string('thumbnail_path')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->enum('category', ['general', 'room-specific', 'equipment', 'safety', 'process'])->default('general');
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('property_instructional_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignUlid('instructional_video_id')->constrained('instructional_videos')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_instructional_videos');
        Schema::dropIfExists('instructional_videos');
    }
};
