<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('action', ['content', 'door_lock'])->default('content')->after('slug');
        });
    }
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('action');
        });
    }
};
