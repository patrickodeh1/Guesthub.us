<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_locks', function (Blueprint $table) {
            $table->boolean('last_known_locked')->nullable()->after('manufacturer');
            $table->timestamp('last_status_at')->nullable()->after('last_known_locked');
        });
    }
    public function down(): void
    {
        Schema::table('property_locks', function (Blueprint $table) {
            $table->dropColumn(['last_known_locked', 'last_status_at']);
        });
    }
};
