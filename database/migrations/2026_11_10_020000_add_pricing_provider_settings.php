<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'pricelabs_api_key')) {
                    $table->text('pricelabs_api_key')->nullable();
                }
                if (! Schema::hasColumn('users', 'wheelhouse_user_api_key')) {
                    $table->text('wheelhouse_user_api_key')->nullable();
                }
                if (! Schema::hasColumn('users', 'wheelhouse_integrators_user_id')) {
                    $table->string('wheelhouse_integrators_user_id', 120)->nullable()->unique();
                }
            });
        }

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table) {
                if (! Schema::hasColumn('properties', 'pricing_provider')) {
                    $table->string('pricing_provider', 20)->nullable();
                }
                if (! Schema::hasColumn('properties', 'wheelhouse_listing_id')) {
                    $table->string('wheelhouse_listing_id', 160)->nullable();
                }
                if (! Schema::hasColumn('properties', 'wheelhouse_channel')) {
                    $table->string('wheelhouse_channel', 50)->nullable();
                }
                if (! Schema::hasColumn('properties', 'wheelhouse_synced_at')) {
                    $table->timestamp('wheelhouse_synced_at')->nullable();
                }
            });

            if (Schema::hasColumn('properties', 'pricelabs_listing_id')) {
                DB::table('properties')
                    ->whereNotNull('pricelabs_listing_id')
                    ->whereNull('pricing_provider')
                    ->update(['pricing_provider' => 'pricelabs']);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            $hasIntegratorId = Schema::hasColumn('users', 'wheelhouse_integrators_user_id');
            $uniqueExists = collect(Schema::getIndexes('users'))
                ->contains(fn (array $index) => $index['name'] === 'users_wheelhouse_integrators_user_id_unique');

            if ($hasIntegratorId && $uniqueExists) {
                Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_wheelhouse_integrators_user_id_unique'));
            }

            foreach (['wheelhouse_integrators_user_id', 'wheelhouse_user_api_key', 'pricelabs_api_key'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    Schema::table('users', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('properties')) {
            foreach (['wheelhouse_synced_at', 'wheelhouse_channel', 'wheelhouse_listing_id', 'pricing_provider'] as $column) {
                if (Schema::hasColumn('properties', $column)) {
                    Schema::table('properties', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
