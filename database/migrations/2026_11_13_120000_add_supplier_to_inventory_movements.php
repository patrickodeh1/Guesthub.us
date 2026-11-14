<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'supplier')) {
                $table->string('supplier', 160)->nullable()->after('reference');
            }
            if (! Schema::hasColumn('inventory_movements', 'ordered_at')) {
                $table->date('ordered_at')->nullable()->after('supplier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'ordered_at')) {
                $table->dropColumn('ordered_at');
            }
            if (Schema::hasColumn('inventory_movements', 'supplier')) {
                $table->dropColumn('supplier');
            }
        });
    }
};
