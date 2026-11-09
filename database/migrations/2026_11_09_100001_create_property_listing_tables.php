<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'stripe_account_id')) {
                    $table->string('stripe_account_id')->nullable()->unique();
                }
                if (! Schema::hasColumn('users', 'stripe_charges_enabled')) {
                    $table->boolean('stripe_charges_enabled')->default(false);
                }
                if (! Schema::hasColumn('users', 'stripe_payouts_enabled')) {
                    $table->boolean('stripe_payouts_enabled')->default(false);
                }
            });
        }

        if (! Schema::hasTable('property_listings')) {
            Schema::create('property_listings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('listing_slug')->unique();
                $table->string('public_title')->nullable();
                $table->longText('public_description')->nullable();
                $table->unsignedSmallInteger('max_guests')->nullable();
                $table->unsignedSmallInteger('bedrooms')->nullable();
                $table->unsignedBigInteger('base_rate_cents')->nullable();
                $table->unsignedBigInteger('cleaning_fee_cents')->nullable();
                $table->decimal('tax_percent', 5, 2)->nullable();
                $table->string('booking_mode')->default('instant');
                $table->unsignedSmallInteger('advance_notice_hours')->default(24);
                $table->time('same_day_cutoff_time')->nullable();
                $table->unsignedSmallInteger('min_nights')->default(1);
                $table->string('cancellation_policy')->default('moderate');
                $table->longText('house_rules')->nullable();
                $table->boolean('lease_enabled')->default(false);
                $table->unsignedBigInteger('lease_application_fee_cents')->nullable();
                $table->text('screening_provider_url')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->timestamps();

                $table->index(['published_at', 'listing_slug']);
            });
        }

        if (! Schema::hasTable('listing_photos')) {
            Schema::create('listing_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('listing_id')->constrained('property_listings')->cascadeOnDelete();
                $table->string('path');
                $table->string('thumbnail_path');
                $table->string('alt')->default('');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['listing_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('host_payment_methods')) {
            Schema::create('host_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
                $table->string('type');
                $table->string('label');
                $table->string('handle')->nullable();
                $table->text('instructions')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index(['owner_id', 'enabled']);
            });
        }
    }

    public function down(): void
    {
        foreach (['host_payment_methods', 'listing_photos', 'property_listings'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        if (Schema::hasTable('users')) {
            $stripeAccountIndexExists = collect(Schema::getIndexes('users'))
                ->contains(fn (array $index) => $index['name'] === 'users_stripe_account_id_unique');
            if ($stripeAccountIndexExists) {
                Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_stripe_account_id_unique'));
            }

            foreach (['stripe_payouts_enabled', 'stripe_charges_enabled', 'stripe_account_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
