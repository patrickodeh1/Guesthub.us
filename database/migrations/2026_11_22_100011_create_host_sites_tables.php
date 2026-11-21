<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('host_sites')) {
            Schema::create('host_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('slug', 40)->unique();
                $table->string('display_name', 120);
                $table->string('logo_path')->nullable();
                $table->string('brand_color', 7)->default('#17221e');
                $table->string('support_email')->nullable();
                $table->boolean('show_powered_by')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('host_site_aliases')) {
            Schema::create('host_site_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('host_site_id')->constrained('host_sites')->cascadeOnDelete();
                $table->string('slug', 40)->unique();
                $table->timestamps();
            });
        }

        $ownerIds = DB::table('properties')
            ->join('property_listings', 'property_listings.property_id', '=', 'properties.id')
            ->whereNotNull('property_listings.published_at')
            ->whereNotNull('properties.owner_id')
            ->distinct()
            ->pluck('properties.owner_id');

        foreach ($ownerIds as $ownerId) {
            $user = DB::table('users')->where('id', $ownerId)->first();
            if (! $user) {
                continue;
            }
            $name = trim((string) (($user->host_name ?? '') ?: $user->name));
            $base = Str::slug($name ?: 'host').'-'.$ownerId;
            $slug = $base;
            $suffix = 2;
            while (DB::table('host_sites')->where('slug', $slug)->where('user_id', '!=', $ownerId)->exists()) {
                $slug = $base.'-'.$suffix++;
            }
            DB::table('host_sites')->insertOrIgnore([
                'user_id' => $ownerId,
                'slug' => $slug,
                'display_name' => $name ?: 'Host',
                'brand_color' => '#17221e',
                'show_powered_by' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('host_site_aliases');
        Schema::dropIfExists('host_sites');
    }
};
