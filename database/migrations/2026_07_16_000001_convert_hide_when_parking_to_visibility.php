<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->string('visibility')->default('all')->after('hide_when_parking');
        });

        DB::table('instruction_steps')->where('hide_when_parking', true)->update(['visibility' => 'non_parkers_only']);

        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropColumn('hide_when_parking');
        });
    }

    public function down(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->boolean('hide_when_parking')->default(false)->after('active');
        });

        DB::table('instruction_steps')->where('visibility', 'non_parkers_only')->update(['hide_when_parking' => true]);

        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
