<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('property_listings', 'lease_fee_refund_policy')) {
                $table->string('lease_fee_refund_policy', 30)->default('unset');
            }
        });
        Schema::table('lease_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_applications', 'fee_policy_accepted_at')) {
                $table->timestamp('fee_policy_accepted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('property_listings', fn (Blueprint $t) => $t->dropColumn('lease_fee_refund_policy'));
        Schema::table('lease_applications', fn (Blueprint $t) => $t->dropColumn('fee_policy_accepted_at'));
    }
};
