<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Feature;
use App\Models\HostFeature;
use App\Models\HostPaymentMethod;
use App\Models\ListingPhoto;
use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Models\PropertyListing;
use App\Models\Setting;
use App\Models\User;
use App\Services\IndexNowService;
use App\Services\QuoteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicListingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Property $property;

    private PropertyListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        Feature::updateOrCreate(
            ['key' => 'direct_booking_site'],
            ['name' => 'Direct booking site', 'is_free' => true, 'active' => true],
        );
        $this->owner = User::factory()->create(['host_name' => 'Bright Homes']);
        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
            'active' => true,
            'address' => '987 Hidden Address Lane',
            'city' => 'San Diego',
            'state' => 'CA',
            'latitude' => '32.7157360',
            'longitude' => '-117.1610870',
            'lockbox_code' => 'PRIVATE-LOCK-CODE',
            'checkin_time' => '16:00',
            'timezone' => 'America/Los_Angeles',
        ]);
        $this->listing = PropertyListing::create([
            'property_id' => $this->property->id,
            'listing_slug' => 'coastal-getaway',
            'public_title' => 'Coastal Getaway',
            'public_description' => 'A sunny and welcoming home near the coast.',
            'max_guests' => 4,
            'bedrooms' => 2,
            'base_rate_cents' => 10000,
            'cleaning_fee_cents' => 500,
            'tax_percent' => '10.00',
            'booking_mode' => 'instant',
            'advance_notice_hours' => 0,
            'min_nights' => 1,
            'cancellation_policy' => 'moderate',
            'published_at' => now(),
        ]);
    }

    public function test_unpublished_and_disabled_listings_are_dark(): void
    {
        $this->listing->update(['published_at' => null]);
        $this->get('/stay/coastal-getaway')->assertNotFound();

        $this->listing->update(['published_at' => now()]);
        Feature::where('key', 'direct_booking_site')->update(['active' => false]);
        $this->get('/stay/coastal-getaway')->assertNotFound();
        $this->get('/stays')->assertDontSee('Coastal Getaway');
    }

    public function test_host_can_disable_free_feature_and_quote_endpoint_requires_an_available_payment_method(): void
    {
        HostFeature::create([
            'user_id' => $this->owner->id,
            'feature_key' => 'direct_booking_site',
            'enabled' => false,
        ]);
        $this->get('/stay/coastal-getaway')->assertNotFound();

        HostFeature::where('feature_key', 'direct_booking_site')->delete();
        $arrival = now('America/Los_Angeles')->addDays(4)->toDateString();
        $departure = now('America/Los_Angeles')->addDays(5)->toDateString();
        $this->getJson(route('public.listing.quote', $this->listing->listing_slug).'?'.http_build_query([
            'check_in' => $arrival,
            'check_out' => $departure,
            'guests' => 1,
        ]))->assertUnprocessable();

        Feature::updateOrCreate(
            ['key' => 'manual_payments'],
            ['name' => 'Manual payments', 'is_free' => true, 'active' => true],
        );
        HostPaymentMethod::create([
            'owner_id' => $this->owner->id,
            'type' => 'venmo',
            'label' => 'Venmo',
            'enabled' => true,
        ]);
        $this->getJson(route('public.listing.quote', $this->listing->listing_slug).'?'.http_build_query([
            'check_in' => $arrival,
            'check_out' => $departure,
            'guests' => 1,
            'payment_method' => 'manual',
        ]))->assertOk()->assertJsonPath('total_cents', 11550);
    }

    public function test_paid_feature_hides_listing_without_current_host_entitlement(): void
    {
        Feature::where('key', 'direct_booking_site')->update(['is_free' => false]);
        $this->get('/stay/coastal-getaway')->assertNotFound();

        HostFeature::create([
            'user_id' => $this->owner->id,
            'feature_key' => 'direct_booking_site',
            'enabled' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $this->get('/stay/coastal-getaway')->assertOk();

        HostFeature::where('feature_key', 'direct_booking_site')->update(['ends_at' => now()->subSecond()]);
        $this->get('/stay/coastal-getaway')->assertNotFound();
    }

    public function test_listing_page_shows_public_content_and_never_renders_private_property_or_guest_data(): void
    {
        ListingPhoto::create([
            'listing_id' => $this->listing->id,
            'path' => 'direct-listings/public.webp',
            'thumbnail_path' => 'direct-listings/public-thumb.webp',
            'alt' => 'Living room',
            'sort_order' => 0,
            'width' => 1200,
            'height' => 800,
        ]);
        Booking::factory()->create([
            'property_id' => $this->property->id,
            'guest_name' => 'Guest Private Name',
            'email' => 'guest-private@example.test',
            'check_in_date' => now()->addDays(20)->toDateString(),
            'check_out_date' => now()->addDays(22)->toDateString(),
        ]);

        $response = $this->get('/stay/coastal-getaway?guests=2');

        $response->assertOk()
            ->assertSee('Coastal Getaway')
            ->assertSee('San Diego')
            ->assertSee('Approximate map location')
            ->assertSee('noindex,follow')
            ->assertDontSee('987 Hidden Address Lane')
            ->assertDontSee('PRIVATE-LOCK-CODE')
            ->assertDontSee('Guest Private Name')
            ->assertDontSee('guest-private@example.test');

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $schema = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('VacationRental', $schema[0]['@type']);
        $this->assertSame(32.72, $schema[0]['geo']['latitude']);
        $this->assertSame(-117.16, $schema[0]['geo']['longitude']);
        $this->assertSame('San Diego', $schema[0]['address']['addressLocality']);
    }

    public function test_public_pages_use_the_configured_site_logo_and_brand_colors(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/site-logo.svg', '<svg></svg>');
        Setting::set('site_logo', 'branding/site-logo.svg');

        $response = $this->get('/stay/coastal-getaway');

        $response->assertOk()
            ->assertSee('src="'.url('file/branding/site-logo.svg').'"', false)
            ->assertSee('--site-brand:#082b49', false)
            ->assertSee('--site-button:#0b2d4d', false);
    }

    public function test_city_host_sitemap_robots_and_llms_endpoints_are_server_rendered(): void
    {
        $this->get('/stays/san-diego')->assertOk()->assertSee('Coastal Getaway');
        $this->get('/h/bright-homes-'.$this->owner->id)->assertOk()->assertSee('Coastal Getaway');
        $this->get('/stays?price_max=100.00&guests=2')->assertOk()
            ->assertSee('Coastal Getaway')
            ->assertSee('noindex,follow');
        $this->get('/stays?price_min=100.01')->assertOk()->assertDontSee('Coastal Getaway');
        $this->get('/stays?guests=5')->assertOk()->assertDontSee('Coastal Getaway');

        $this->listing->update([
            'lease_enabled' => true,
            'screening_provider_url' => 'https://screening.example/apply',
        ]);
        $this->get('/stay/coastal-getaway')
            ->assertOk()
            ->assertSee('href="https://screening.example/apply"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $sitemap = $this->get('/sitemap.xml');
        $sitemap
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/stay/coastal-getaway');
        $this->assertNotFalse(simplexml_load_string($sitemap->getContent()));
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('User-agent: GPTBot')
            ->assertSee('Disallow: /guest');
        $this->get('/llms.txt')->assertOk()->assertSee('published short-term rental listings');
    }

    public function test_quote_uses_ledger_rates_cents_tax_and_card_only_processing_fee(): void
    {
        Setting::putValue('processing_fee_percent', '3');
        $arrival = now('America/Los_Angeles')->addDays(4)->toDateString();
        $secondNight = now('America/Los_Angeles')->addDays(5)->toDateString();
        PropertyAvailability::create([
            'property_id' => $this->property->id,
            'date' => $arrival,
            'is_available' => true,
            'status' => 'available',
            'rate' => '120.00',
        ]);
        PropertyAvailability::create([
            'property_id' => $this->property->id,
            'date' => $secondNight,
            'is_available' => true,
            'status' => 'available',
            'rate' => null,
        ]);

        $quotes = app(QuoteService::class);
        $card = $quotes->quote($this->listing->fresh('property'), $arrival, now('America/Los_Angeles')->addDays(6)->toDateString(), 2);
        $manual = $quotes->quote($this->listing->fresh('property'), $arrival, now('America/Los_Angeles')->addDays(6)->toDateString(), 2, 'manual');

        $this->assertSame(22000, $card['subtotal_cents']);
        $this->assertSame(25493, $card['total_cents']);
        $this->assertSame(24750, $manual['total_cents']);
        $this->assertSame(2, $card['nights']);
        $this->assertContains('Card processing fee', array_column($card['line_items'], 'label'));
        $this->assertNotContains('Card processing fee', array_column($manual['line_items'], 'label'));
    }

    public function test_quote_enforces_availability_minimum_maximum_guests_and_conflicts(): void
    {
        $arrival = now('America/Los_Angeles')->addDays(4)->toDateString();
        $departure = now('America/Los_Angeles')->addDays(6)->toDateString();

        PropertyAvailability::create([
            'property_id' => $this->property->id,
            'date' => $arrival,
            'is_available' => true,
            'status' => 'available',
            'min_stay_arrival' => 3,
            'max_stay' => 2,
        ]);

        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 2);
            $this->fail('Stay restrictions must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_out', $exception->errors());
        }

        $this->property->availabilities()->whereDate('date', $arrival)->update([
            'min_stay_arrival' => 1,
            'min_stay_through' => 3,
        ]);
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 2);
            $this->fail('Minimum-through restrictions must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_out', $exception->errors());
        }

        $this->property->availabilities()->whereDate('date', $arrival)->update([
            'min_stay_arrival' => 1,
            'min_stay_through' => 1,
            'max_stay' => 1,
        ]);
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 2);
            $this->fail('Maximum-stay restrictions must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('maximum', $exception->errors()['check_out'][0]);
        }

        $this->listing->update(['max_guests' => 1]);
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 2);
            $this->fail('The guest limit must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('guests', $exception->errors());
        }

        $this->listing->update(['max_guests' => 4]);
        $this->property->availabilities()->whereDate('date', $arrival)->update([
            'min_stay_arrival' => null,
            'max_stay' => null,
            'stop_sell' => true,
        ]);
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 2);
            $this->fail('Stop-sell dates must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_in', $exception->errors());
        }

        $this->property->availabilities()->whereDate('date', $arrival)->delete();
        Booking::factory()->create([
            'property_id' => $this->property->id,
            'status' => Booking::STATUS_PENDING_PAYMENT,
            'check_in_date' => $arrival,
            'check_out_date' => $departure,
        ]);
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 2);
            $this->fail('Pending-payment date holds must be respected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_in', $exception->errors());
        }
    }

    public function test_quote_respects_closed_arrival_departure_advance_notice_and_same_day_cutoff(): void
    {
        $arrival = now('America/Los_Angeles')->addDays(4)->toDateString();
        $departure = now('America/Los_Angeles')->addDays(5)->toDateString();
        PropertyAvailability::create([
            'property_id' => $this->property->id,
            'date' => $arrival,
            'is_available' => true,
            'status' => 'available',
            'closed_to_arrival' => true,
        ]);

        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 1);
            $this->fail('Closed arrival dates must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_in', $exception->errors());
        }

        $this->property->availabilities()->whereDate('date', $arrival)->update(['closed_to_arrival' => false]);
        $this->property->availabilities()->create([
            'date' => $departure,
            'is_available' => true,
            'status' => 'available',
            'closed_to_departure' => true,
        ]);
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), $arrival, $departure, 1);
            $this->fail('Closed departure dates must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_out', $exception->errors());
        }

        $this->property->availabilities()->whereDate('date', $departure)->delete();
        $this->listing->update(['advance_notice_hours' => 72]);
        try {
            app(QuoteService::class)->quote(
                $this->listing->fresh('property'),
                now('America/Los_Angeles')->addDays(2)->toDateString(),
                now('America/Los_Angeles')->addDays(3)->toDateString(),
                1,
            );
            $this->fail('Advance-notice restrictions must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_in', $exception->errors());
        }

        $this->listing->update(['advance_notice_hours' => 0, 'same_day_cutoff_time' => '10:00:00']);
        Carbon::setTestNow(Carbon::parse('2026-11-09 12:00:00', 'America/Los_Angeles'));
        try {
            app(QuoteService::class)->quote($this->listing->fresh('property'), '2026-11-09', '2026-11-10', 1);
            $this->fail('The same-day cutoff must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('check_in', $exception->errors());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_same_day_arrival_is_allowed_at_the_cutoff_time(): void
    {
        $this->listing->update(['advance_notice_hours' => 0, 'same_day_cutoff_time' => '10:00:00']);
        Carbon::setTestNow(Carbon::parse('2026-11-09 10:00:00', 'America/Los_Angeles'));

        try {
            $quote = app(QuoteService::class)->quote(
                $this->listing->fresh('property'),
                '2026-11-09',
                '2026-11-10',
                1,
            );
            $this->assertSame(1, $quote['nights']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_indexnow_is_optional_and_public_key_file_is_served_only_for_configured_key(): void
    {
        Http::fake();
        config(['services.indexnow.key' => null]);
        app(IndexNowService::class)->notifyListing($this->listing);
        Http::assertNothingSent();

        config(['services.indexnow.key' => 'indexnow-test-key-123456']);
        app(IndexNowService::class)->notifyListing($this->listing);
        Http::assertSent(fn ($request) => $request['key'] === 'indexnow-test-key-123456'
            && $request['urlList'][0] === route('public.listing.show', $this->listing->listing_slug));

        $this->get('/indexnow-test-key-123456.txt')->assertOk()->assertSeeText('indexnow-test-key-123456');
        $this->get('/wrong-indexnow-key.txt')->assertNotFound();
    }

    public function test_indexnow_is_pinged_when_an_owner_unpublishes_a_listing(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole('owner');
        config(['services.indexnow.key' => 'indexnow-test-key-123456']);
        Http::fake();

        $this->actingAs($this->owner)
            ->put(route('admin.properties.listing.save', $this->property), [
                'published' => false,
                'booking_mode' => 'instant',
                'advance_notice_hours' => 24,
                'min_nights' => 1,
                'cancellation_policy' => 'moderate',
            ])
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request['urlList'][0] === route('public.listing.show', $this->listing->listing_slug));
    }
}
