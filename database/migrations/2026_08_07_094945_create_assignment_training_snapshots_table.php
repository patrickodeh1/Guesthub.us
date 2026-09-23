<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_training_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_session_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            // ULID for instructional videos
            $table->char('instructional_video_id', 26)->nullable();
            
            $table->integer('required_version')->nullable();
            $table->boolean('is_required_before_start')->default(false);
            $table->integer('display_order')->default(0);
            
            $table->timestamps();

            $table->unique(['cleaning_session_id', 'task_id', 'instructional_video_id'], 'assignment_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_training_snapshots');
    }
};
