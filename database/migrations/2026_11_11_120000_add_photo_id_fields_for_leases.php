<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_applications', 'id_type')) {
                $table->string('id_type', 20)->default('state_id');
            }
            if (! Schema::hasColumn('lease_applications', 'id_back_scan_path')) {
                $table->string('id_back_scan_path')->nullable();
            }
        });
        Schema::table('property_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('property_listings', 'lease_allow_passport')) {
                $table->boolean('lease_allow_passport')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_applications', function (Blueprint $table) {
            $table->dropColumn(['id_type', 'id_back_scan_path']);
        });
        Schema::table('property_listings', function (Blueprint $table) {
            $table->dropColumn('lease_allow_passport');
        });
    }
};
