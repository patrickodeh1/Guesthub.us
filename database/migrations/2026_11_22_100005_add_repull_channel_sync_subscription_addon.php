<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('features')->insertOrIgnore([
            'key' => 'repull_channel_sync',
            'name' => 'OTA channel sync (Repull)',
            'is_free' => false,
            'price_cents' => 0,
            'active' => true,
            'billing_unit' => 'per_channel_listing',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('features')
            ->where('key', 'repull_channel_sync')
            ->update(['billing_unit' => 'per_channel_listing', 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('host_features')->where('feature_key', 'repull_channel_sync')->delete();
        DB::table('package_features')->where('feature_key', 'repull_channel_sync')->delete();
        DB::table('features')->where('key', 'repull_channel_sync')->delete();
    }
};
