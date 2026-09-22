<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->enum('action', ['content', 'unlock_door', 'lock_door'])->default('content')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropColumn('action');
        });
    }
};
