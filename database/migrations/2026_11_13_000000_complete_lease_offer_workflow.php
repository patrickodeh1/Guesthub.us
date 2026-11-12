<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('property_listings', 'listing_type')) {
                $table->string('listing_type', 20)->default('both')->after('lease_enabled');
            }
            if (! Schema::hasColumn('property_listings', 'lease_auto_route_29_nights')) {
                $table->boolean('lease_auto_route_29_nights')->default(true)->after('listing_type');
            }
        });

        Schema::table('lease_offers', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_offers', 'currency')) {
                $table->string('currency', 3)->default('usd');
            }
            if (! Schema::hasColumn('lease_offers', 'term_months')) {
                $table->unsignedSmallInteger('term_months')->default(1);
            }
            if (! Schema::hasColumn('lease_offers', 'guest_payment_reference')) {
                $table->string('guest_payment_reference', 255)->nullable();
            }
        });

        Schema::table('charges', function (Blueprint $table) {
            if (! Schema::hasColumn('charges', 'lease_offer_id')) {
                $table->unsignedBigInteger('lease_offer_id')->nullable()->index();
            }
            if (! Schema::hasColumn('charges', 'lease_installment_id')) {
                $table->unsignedBigInteger('lease_installment_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        foreach (['property_listings' => ['lease_auto_route_29_nights', 'listing_type'], 'lease_offers' => ['guest_payment_reference', 'term_months', 'currency'], 'charges' => ['lease_installment_id', 'lease_offer_id']] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) continue;
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
