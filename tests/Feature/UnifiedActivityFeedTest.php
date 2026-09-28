<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Property;
use App\Models\User;
use App\Services\UnifiedActivityFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_interleaves_both_sources_and_paginates_the_combined_total(): void
    {
        $portal = ActivityLog::create([
            'action' => 'portal_action',
            'description' => 'Portal event',
            'actor_type' => 'admin',
        ]);
        DB::table('activity_logs')->where('id', $portal->id)->update([
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        DB::table('activity_log')->insert([
            'log_name' => 'ops',
            'description' => 'Ops event',
            'event' => 'updated',
            'subject_type' => Property::class,
            'subject_id' => 1,
            'properties' => json_encode(['attributes' => ['name' => 'Updated']]),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $feed = app(UnifiedActivityFeed::class)->paginate([], null, 2);
        $this->assertSame(2, $feed->total());
        $this->assertSame('ops', $feed->first()->source);
        $this->assertSame('portal', $feed->last()->source);
    }

    public function test_owner_only_sees_activity_for_owned_properties(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));
        $owned = Property::factory()->create(['owner_id' => $owner->id]);
        $other = Property::factory()->create();

        ActivityLog::create([
            'action' => 'owned',
            'description' => 'Owned property event',
            'property_id' => $owned->id,
        ]);
        ActivityLog::create([
            'action' => 'other',
            'description' => 'Other property event',
            'property_id' => $other->id,
        ]);

        $feed = app(UnifiedActivityFeed::class)->paginate([], $owner);

        $this->assertCount(1, $feed->items());
        $this->assertSame('Owned property event', $feed->first()->description);
    }

    public function test_admin_with_owner_role_still_sees_the_full_feed(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole([
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']),
            Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']),
        ]);

        ActivityLog::create([
            'action' => 'admin_event',
            'description' => 'Admin event',
        ]);

        $feed = app(UnifiedActivityFeed::class)->paginate([], $admin);

        $this->assertGreaterThanOrEqual(1, $feed->total());
        $this->assertSame('Admin event', $feed->first()->description);
    }

    public function test_feed_filters_source_and_text(): void
    {
        ActivityLog::create([
            'action' => 'portal_action',
            'description' => 'Unique portal event',
            'module' => 'guests',
        ]);
        DB::table('activity_log')->insert([
            'log_name' => 'ops',
            'description' => 'Unique cleaning event',
            'event' => 'created',
            'properties' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $feed = app(UnifiedActivityFeed::class)->paginate([
            'source' => 'ops',
            'search' => 'cleaning',
        ]);

        $this->assertCount(1, $feed->items());
        $this->assertSame('ops', $feed->first()->source);
    }

    public function test_activity_table_schemas_are_unchanged_and_both_sources_exist(): void
    {
        $this->assertTrue(Schema::hasTable('activity_logs'));
        $this->assertTrue(Schema::hasTable('activity_log'));
        $this->assertContains('metadata', Schema::getColumnListing('activity_logs'));
        $this->assertContains('properties', Schema::getColumnListing('activity_log'));
    }
}
