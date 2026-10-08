<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('instruction_steps', 'show_before_gps')) {
            Schema::table('instruction_steps', function (Blueprint $table) {
                $table->boolean('show_before_gps')->default(false)->after('visibility');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('instruction_steps', 'show_before_gps')) {
            Schema::table('instruction_steps', function (Blueprint $table) {
                $table->dropColumn('show_before_gps');
            });
        }
    }
};
