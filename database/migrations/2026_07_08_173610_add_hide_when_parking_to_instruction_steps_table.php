<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->boolean('hide_when_parking')->default(false)->after('active');
        });
    }
    public function down(): void
    {
        Schema::table('instruction_steps', function (Blueprint $table) {
            $table->dropColumn('hide_when_parking');
        });
    }
};
