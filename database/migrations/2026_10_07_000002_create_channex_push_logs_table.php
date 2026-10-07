<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channex_push_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channex_property_id', 64)->index();
            $table->string('path', 32);
            $table->unsignedInteger('value_count');
            $table->unsignedSmallInteger('status')->nullable();
            $table->boolean('success');
            $table->string('task_ids', 500)->nullable();
            $table->text('payload')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channex_push_logs');
    }
};
