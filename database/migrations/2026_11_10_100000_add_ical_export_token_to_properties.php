<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('properties')) {
            return;
        }

        if (! Schema::hasColumn('properties', 'ical_export_token')) {
            Schema::table('properties', function (Blueprint $table): void {
                $table->string('ical_export_token', 64)->nullable()->unique();
            });
        }

        DB::table('properties')
            ->whereNull('ical_export_token')
            ->orderBy('id')
            ->chunkById(100, function ($properties): void {
                foreach ($properties as $property) {
                    DB::table('properties')
                        ->where('id', $property->id)
                        ->whereNull('ical_export_token')
                        ->update(['ical_export_token' => Str::random(64)]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('properties') || ! Schema::hasColumn('properties', 'ical_export_token')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table): void {
            $table->dropUnique(['ical_export_token']);
            $table->dropColumn('ical_export_token');
        });
    }
};
