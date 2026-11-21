<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_sites', function (Blueprint $table) {
            if (! Schema::hasColumn('host_sites', 'custom_domain')) {
                $table->string('custom_domain', 253)->nullable()->unique();
            }
            if (! Schema::hasColumn('host_sites', 'domain_verification_token')) {
                $table->string('domain_verification_token', 64)->nullable();
            }
            if (! Schema::hasColumn('host_sites', 'domain_verified_at')) {
                $table->timestamp('domain_verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('host_sites', function (Blueprint $table) {
            if (Schema::hasColumn('host_sites', 'custom_domain')) {
                $table->dropUnique(['custom_domain']);
                $table->dropColumn('custom_domain');
            }
            foreach (['domain_verification_token', 'domain_verified_at'] as $column) {
                if (Schema::hasColumn('host_sites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
