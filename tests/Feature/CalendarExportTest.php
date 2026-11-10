<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Feature;
use App\Models\HostFeature;
use App\Models\Property;
use App\Models\User;
use App\Services\Pms\AvailabilityLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Sabre\VObject\Reader;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        Feature::updateOrCreate(
            ['key' => 'calendar_export'],
            ['name' => 'Calendar export', 'is_free' => true, 'active' => true],
        );
        $this->owner = User::factory()->create();
        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
            'active' => true,
        ]);
    }

    public function test_feed_is_valid_anonymous_and_includes_active_bookings_and_manual_blocks(): void
    {
        $directBooking = $this->booking('direct', 'Direct', Booking::STATUS_PENDING_PAYMENT, 10);
        $this->booking('channex', 'Airbnb', 'pending', 15);
        $this->booking('channex', 'Vrbo', 'cancelled', 20);
        $blockDate = now()->addDays(25)->toDateString();
        app(AvailabilityLedger::class)->setDates(
            $this->property,
            [$blockDate],
            ['status' => 'blocked'],
            'manual',
        );

        $response = $this->get($this->feedUrl());

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('Private Calendar Guest')
            ->assertDontSee('private-calendar@example.test')
            ->assertDontSee((string) $this->property->address)
            ->assertDontSee($this->property->name)
            ->assertDontSee($directBooking->booking_id);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        Reader::read($response->getContent());
        $this->assertSame(3, substr_count($response->getContent(), 'BEGIN:VEVENT'));
        $this->assertSame(3, substr_count($response->getContent(), 'SUMMARY:Reserved'));
        $this->assertStringContainsString(
            'DTEND;VALUE=DATE:'.Carbon::parse($blockDate)->addDay()->format('Ymd'),
            $response->getContent(),
        );
    }

    public function test_feed_excludes_bookings_from_the_requested_origin(): void
    {
        $airbnb = $this->booking('channex', 'Airbnb', 'pending', 10);
        $vrbo = $this->booking('channex', 'VRBO', 'pending', 15);
        $other = $this->booking('channex', 'Booking.com', 'pending', 20);
        $direct = $this->booking('direct', 'Direct', Booking::STATUS_PENDING_PAYMENT, 25);

        $airbnbFeed = $this->get(route('ical.export', ['token' => $this->property->ical_export_token, 'for' => 'airbnb']));
        $vrboFeed = $this->get(route('ical.export', ['token' => $this->property->ical_export_token, 'for' => 'vrbo']));
        $otherFeed = $this->get(route('ical.export', ['token' => $this->property->ical_export_token, 'for' => 'other']));

        foreach ([
            [$airbnbFeed, $airbnb, $vrbo, $other, $direct],
            [$vrboFeed, $vrbo, $airbnb, $other, $direct],
            [$otherFeed, $other, $airbnb, $vrbo, $direct],
        ] as [$response, $excluded, $includedOne, $includedTwo, $includedThree]) {
            $response->assertOk();
            $feed = $response->getContent();
            $this->assertSame(3, substr_count($feed, 'BEGIN:VEVENT'));
            $this->assertStringNotContainsString($this->bookingUid($excluded), $feed);
            $this->assertStringContainsString($this->bookingUid($includedOne), $feed);
            $this->assertStringContainsString($this->bookingUid($includedTwo), $feed);
            $this->assertStringContainsString($this->bookingUid($includedThree), $feed);
        }
    }

    public function test_new_booking_invalidates_cached_feed_immediately(): void
    {
        $url = $this->feedUrl();
        $this->get($url)->assertOk()->assertDontSee('BEGIN:VEVENT');

        $booking = $this->booking('direct', 'Direct', Booking::STATUS_PENDING_PAYMENT, 12);

        $this->get($url)
            ->assertOk()
            ->assertSee($this->bookingUid($booking))
            ->assertSee('SUMMARY:Reserved');
    }

    public function test_feed_is_hidden_when_host_disables_calendar_export(): void
    {
        HostFeature::create([
            'user_id' => $this->owner->id,
            'feature_key' => 'calendar_export',
            'enabled' => false,
            'source' => 'manual',
        ]);

        $this->get($this->feedUrl())->assertNotFound();
    }

    public function test_invalid_channel_filter_is_rejected(): void
    {
        $this->get(route('ical.export', ['token' => $this->property->ical_export_token, 'for' => 'airbnbish']))
            ->assertBadRequest();
    }

    public function test_owner_availability_page_shows_copy_link_and_refresh_caveat(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole('owner');

        $this->actingAs($this->owner)
            ->get(route('admin.properties.availability.index', $this->property))
            ->assertOk()
            ->assertSee(route('ical.export', ['token' => $this->property->ical_export_token]))
            ->assertSee('Copy link')
            ->assertSee('not real-time')
            ->assertSee('Airbnb or Vrbo');
    }

    private function booking(string $source, string $platform, string $status, int $arrivalOffset): Booking
    {
        return Booking::factory()
            ->for($this->property)
            ->create([
                'booking_id' => 'CAL-'.strtoupper(Str::random(8)),
                'source' => $source,
                'booking_platform' => $platform,
                'status' => $status,
                'guest_name' => 'Private Calendar Guest',
                'email' => 'private-calendar@example.test',
                'check_in_date' => now()->addDays($arrivalOffset)->toDateString(),
                'check_out_date' => now()->addDays($arrivalOffset + 2)->toDateString(),
            ]);
    }

    private function feedUrl(): string
    {
        return route('ical.export', ['token' => $this->property->ical_export_token]);
    }

    private function bookingUid(Booking $booking): string
    {
        return substr(hash_hmac('sha256', 'booking:'.$booking->id, (string) config('app.key')), 0, 32).'@guesthub.us';
    }
}
