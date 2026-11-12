<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'lease_agreement')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->longText('lease_agreement')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('properties', 'lease_agreement')) {
            Schema::table('properties', fn (Blueprint $table) => $table->dropColumn('lease_agreement'));
        }
    }
};
