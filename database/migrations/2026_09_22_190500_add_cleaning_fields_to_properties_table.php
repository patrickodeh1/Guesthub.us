<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_path', 2048)->nullable();
            $table->unsignedTinyInteger('beds')->default(0);
            $table->unsignedTinyInteger('baths')->default(0);
            $table->unsignedInteger('geo_radius_m')->default(150);
            $table->string('ical_url')->nullable();
            $table->string('vrbo_ical_url', 1000)->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['deactivated_by']);
            $table->dropColumn([
                'owner_id',
                'photo_path',
                'beds',
                'baths',
                'geo_radius_m',
                'ical_url',
                'vrbo_ical_url',
                'deactivated_at',
                'deactivated_by',
            ]);
        });
    }
};
