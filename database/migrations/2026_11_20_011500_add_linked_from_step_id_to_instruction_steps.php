<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('instruction_steps', 'linked_from_step_id')) {
            return;
        }
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_from_step_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropColumn('linked_from_step_id');
        });
    }
};
