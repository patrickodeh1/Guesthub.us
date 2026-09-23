<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cleaning_session_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            // Assuming instructional_videos uses ULID/UUID based on the model.
            $table->char('instructional_video_id', 26)->nullable();
            
            $table->integer('training_version')->nullable();
            
            $table->string('status')->default('not_started'); // not_started, in_progress, completed
            $table->integer('progress')->default(0); // 0-100
            
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // A cleaner can only have one completion record for a specific item (either global, property-specific, or session-specific).
            // To ensure database integrity and avoid duplicates, we use a unique index.
            // Since some fields are nullable, this index acts as a loose constraint.
            $table->unique(['user_id', 'property_id', 'cleaning_session_id', 'task_id', 'instructional_video_id', 'training_version'], 'training_completion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_completions');
    }
};
