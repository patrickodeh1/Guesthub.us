<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_applications', function (Blueprint $table) {
            foreach (['id_front_approved_at', 'id_back_approved_at'] as $c) {
                if (! Schema::hasColumn('lease_applications', $c)) {
                    $table->timestamp($c)->nullable();
                }
            }
            foreach (['id_front_declined_reason', 'id_back_declined_reason'] as $c) {
                if (! Schema::hasColumn('lease_applications', $c)) {
                    $table->string($c, 1000)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_applications', fn (Blueprint $t) => $t->dropColumn(['id_front_approved_at', 'id_back_approved_at', 'id_front_declined_reason', 'id_back_declined_reason']));
    }
};
