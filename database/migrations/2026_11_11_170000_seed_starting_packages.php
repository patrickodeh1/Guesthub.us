<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }
        $now = now();
        $prev = null;
        foreach ([['basic', 'Basic'], ['pro', 'Pro'], ['enhanced', 'Enhanced']] as $i => [$key, $name]) {
            DB::table('packages')->insertOrIgnore([
                'key' => $key, 'name' => $name, 'price_cents' => 0, 'parent_id' => $prev,
                'sort_order' => $i + 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $prev = DB::table('packages')->where('key', $key)->value('id');
        }
    }

    public function down(): void
    {
        DB::table('packages')->whereIn('key', ['basic', 'pro', 'enhanced'])->delete();
    }
};
