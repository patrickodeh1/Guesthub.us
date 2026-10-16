<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('instruction_steps', 'requires_photo')) {
            Schema::table('instruction_steps', function (Blueprint $table) {
                $table->boolean('requires_photo')->default(false);
            });
        }

        Schema::dropIfExists('session_access_photos');
        Schema::create('session_access_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instruction_step_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->unique(['cleaning_session_id', 'instruction_step_id'], 'sap_session_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_access_photos');
        if (Schema::hasColumn('instruction_steps', 'requires_photo')) {
            Schema::table('instruction_steps', function (Blueprint $table) {
                $table->dropColumn('requires_photo');
            });
        }
    }
};
