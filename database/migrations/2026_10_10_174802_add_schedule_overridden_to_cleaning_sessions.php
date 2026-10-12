<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $t) {
            $t->boolean('schedule_overridden')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cleaning_sessions', function (Blueprint $t) {
            $t->dropColumn('schedule_overridden');
        });
    }
};
