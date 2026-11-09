<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_FEATURES = [
        ['key' => 'direct_booking_site', 'name' => 'Direct booking site'],
        ['key' => 'manual_payments', 'name' => 'Manual payments'],
        ['key' => 'lease_applications', 'name' => 'Lease applications'],
        ['key' => 'calendar_export', 'name' => 'Calendar export'],
        ['key' => 'wheelhouse_pricing', 'name' => 'Wheelhouse pricing'],
        ['key' => 'pricelabs_pricing', 'name' => 'PriceLabs pricing'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->boolean('is_free')->default(true);
                $table->unsignedInteger('price_cents')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('host_features')) {
            Schema::create('host_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('feature_key');
                $table->boolean('enabled')->default(true);
                $table->string('source')->default('manual');
                $table->unsignedInteger('price_cents')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'feature_key']);
                $table->foreign('feature_key')->references('key')->on('features')->cascadeOnDelete();
                $table->index(['feature_key', 'enabled', 'starts_at', 'ends_at']);
            });
        }

        if (Schema::hasTable('features') && Schema::hasColumn('features', 'key')) {
            $now = now();
            DB::table('features')->insertOrIgnore(array_map(
                fn (array $feature) => $feature + [
                    'is_free' => true,
                    'price_cents' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                self::DEFAULT_FEATURES,
            ));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('host_features')) {
            Schema::drop('host_features');
        }

        if (Schema::hasTable('features')) {
            Schema::drop('features');
        }
    }
};
