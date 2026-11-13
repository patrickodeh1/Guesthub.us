<?php

namespace Tests\Feature;

use App\Mail\DirectBookingMail;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\Feature;
use App\Models\HostPaymentMethod;
use App\Models\PaymentReceipt;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingConflictService;
use App\Services\DirectBookingCancellationPolicy;
use App\Services\DirectBookingService;
use App\Services\QuoteService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectBookingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Property $property;

    private PropertyListing $listing;

    private HostPaymentMethod $manualMethod;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Feature::updateOrCreate(
            ['key' => 'direct_booking_site'],
            ['name' => 'Direct booking site', 'is_free' => true, 'active' => true],
        );
        Feature::updateOrCreate(
            ['key' => 'manual_payments'],
            ['name' => 'Manual payments', 'is_free' => true, 'active' => true],
        );

        $this->owner = User::factory()->create(['host_name' => 'Coastal Host']);
        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
            'active' => true,
            'city' => 'San Diego',
            'timezone' => 'America/Los_Angeles',
            'checkin_time' => '16:00',
        ]);
        $this->listing = PropertyListing::create([
            'property_id' => $this->property->id,
            'listing_slug' => 'direct-coastal',
            'public_title' => 'Direct Coastal Stay',
            'public_description' => 'A welcoming property for a relaxing visit.',
            'max_guests' => 4,
            'base_rate_cents' => 12000,
            'cleaning_fee_cents' => 2500,
            'tax_percent' => '10.00',
            'booking_mode' => 'instant',
            'advance_notice_hours' => 0,
            'min_nights' => 1,
            'cancellation_policy' => 'moderate',
            'published_at' => now(),
        ]);
        $this->manualMethod = HostPaymentMethod::create([
            'owner_id' => $this->owner->id,
            'type' => 'venmo',
            'label' => 'Venmo',
            'handle' => '@coastal-host',
            'instructions' => 'Send payment and use your booking reference.',
            'enabled' => true,
        ]);
    }

    public function test_manual_checkout_recomputes_server_price_holds_nights_and_sends_payment_instructions(): void
    {
        $arrival = now('America/Los_Angeles')->addDays(8)->toDateString();
        $departure = now('America/Los_Angeles')->addDays(10)->toDateString();

        $response = $this->post(route('public.checkout.start', $this->listing->listing_slug), [
            'guest_name' => 'Taylor Guest',
            'email' => 'taylor@example.test',
            'phone' => '8012345678',
            'phone_country_code' => '+234',
            'check_in' => $arrival,
            'check_out' => $departure,
            'guests' => 2,
            'payment_method' => 'manual',
            'host_payment_method_id' => $this->manualMethod->id,
            'terms' => '1',
            'total_cents' => 1,
        ]);

        $booking = Booking::query()->where('email', 'taylor@example.test')->firstOrFail();
        $response->assertRedirect(route('public.checkout.pay', [$booking->booking_id, $booking->token]));
        $this->assertSame(24000, $booking->accommodation_cents);
        $this->assertSame(0, $booking->cleaning_fee_cents);
        $this->assertSame(2400, $booking->tax_cents);
        $this->assertSame(0, $booking->processing_fee_cents);
        $this->assertSame(26400, $booking->total_cents);
        $this->assertSame('+2348012345678', $booking->phone);
        $this->assertSame(Booking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertSame('manual', $booking->payment_method);
        $this->assertFalse($this->property->availabilities()->whereDate('date', $arrival)->firstOrFail()->is_available);
        $this->assertEqualsWithDelta(
            $booking->created_at->copy()->addHours(24)->timestamp,
            $booking->hold_expires_at->timestamp,
            1,
        );
        $this->assertNull($booking->payment_confirmed_at);
        $this->assertDatabaseCount('cleaning_sessions', 0);

        $status = $this->get(route('public.checkout.pay', [$booking->booking_id, $booking->token]));
        $status->assertOk()
            ->assertSee('@coastal-host')
            ->assertSee('Send payment and use your booking reference.')
            ->assertSee($booking->booking_id)
            ->assertSee("I've sent the payment")
            ->assertSee('name="receipt"', false);
        Mail::assertSent(DirectBookingMail::class, 2);
    }

    public function test_host_can_embed_a_direct_booking_form_on_an_external_website(): void
    {
        $this->get(route('public.listing.embed', $this->listing->listing_slug))
            ->assertOk()
            ->assertSee('id="embed-booking-form"', false)
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *;');
    }

    public function test_checkout_page_shows_a_live_quote_summary_for_selected_dates(): void
    {
        $checkIn = now('America/Los_Angeles')->addDays(9)->toDateString();
        $checkOut = now('America/Los_Angeles')->addDays(11)->toDateString();

        $this->get(route('public.checkout.show', [
            'listing_slug' => $this->listing->listing_slug,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
        ]))->assertOk()
            ->assertSee('id="sum-total"', false)
            ->assertSee('id="quote-lines"', false)
            ->assertSee('Calculating your stay', false);
    }

    public function test_checkout_phone_uses_the_selected_country_code(): void
    {
        $this->get(route('public.checkout.show', $this->listing->listing_slug))
            ->assertOk()
            ->assertSee('guest-phone-country-button');

        $checkIn = now('America/Los_Angeles')->addDays(9)->toDateString();
        $checkOut = now('America/Los_Angeles')->addDays(11)->toDateString();
        $payload = $this->manualCheckoutPayload($checkIn, $checkOut);
        $payload['phone'] = '8012345678';
        $payload['phone_country_code'] = '+234';
        $this->post(route('public.checkout.start', $this->listing->listing_slug), $payload)->assertRedirect();

        $booking = Booking::query()->where('email', 'taylor@example.test')->firstOrFail();
        $this->assertSame('+2348012345678', $booking->phone);
    }

    public function test_card_processing_fee_is_not_applied_to_manual_payment_quotes(): void
    {
        Setting::putValue('processing_fee_percent', '5');
        $arrival = now('America/Los_Angeles')->addDays(13)->toDateString();
        $departure = now('America/Los_Angeles')->addDays(15)->toDateString();
        $quotes = app(QuoteService::class);
        $card = $quotes->quote($this->listing->fresh('property'), $arrival, $departure, 2, 'card');
        $manual = $quotes->quote($this->listing->fresh('property'), $arrival, $departure, 2, 'manual');

        $this->assertContains('Card processing fee', array_column($card['line_items'], 'label'));
        $this->assertNotContains('Card processing fee', array_column($manual['line_items'], 'label'));
        $this->assertGreaterThan($manual['total_cents'], $card['total_cents']);
    }

    public function test_consecutive_checkout_requests_for_same_dates_only_create_one_hold(): void
    {
        $payload = $this->manualCheckoutPayload(
            now('America/Los_Angeles')->addDays(12)->toDateString(),
            now('America/Los_Angeles')->addDays(14)->toDateString(),
        );

        $this->post(route('public.checkout.start', $this->listing->listing_slug), $payload)->assertRedirect();
        $this->post(route('public.checkout.start', $this->listing->listing_slug), $payload)->assertSessionHasErrors('check_in');
        $this->assertSame(1, Booking::query()->where('source', 'direct')->count());
    }

    public function test_expired_manual_hold_is_cancelled_and_dates_are_released(): void
    {
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(20)->toDateString(),
            now('America/Los_Angeles')->addDays(22)->toDateString(),
        );
        $booking->update(['hold_expires_at' => now()->subMinute()]);

        $this->artisan('directbookings:release-expired-holds')->assertSuccessful();

        $booking->refresh();
        $this->assertSame('expired', $booking->status);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertNull(BookingConflictService::find(
            $this->property->id,
            $booking->check_in_date,
            $booking->check_out_date,
        ));
        $night = $this->property->availabilities()->whereDate('date', $booking->check_in_date)->firstOrFail();
        $this->assertTrue($night->is_available);
        Mail::assertSent(DirectBookingMail::class, 3);
    }

    public function test_request_to_book_requires_host_approval_before_payment_and_then_expires_after_one_day(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole('owner');
        $this->listing->update(['booking_mode' => 'request']);
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(30)->toDateString(),
            now('America/Los_Angeles')->addDays(32)->toDateString(),
        );

        $this->assertTrue($booking->direct_booking_request);
        $this->assertNull($booking->direct_booking_approved_at);
        $this->assertDatabaseCount('cleaning_sessions', 0);

        $this->actingAs($this->owner)
            ->post(route('admin.guests.direct-approve', $booking))
            ->assertRedirect();

        $booking->refresh();
        $this->assertNotNull($booking->direct_booking_approved_at);
        $this->assertSame(Booking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertNull($booking->payment_confirmed_at);
        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $booking->hold_expires_at->timestamp,
            2,
        );
        $this->get(route('public.checkout.pay', [$booking->booking_id, $booking->token]))
            ->assertOk()
            ->assertSee('Manual payment instructions');
    }

    public function test_request_decline_releases_the_dates(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole('owner');
        $this->listing->update(['booking_mode' => 'request']);
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(35)->toDateString(),
            now('America/Los_Angeles')->addDays(37)->toDateString(),
        );

        $this->actingAs($this->owner)
            ->post(route('admin.guests.direct-decline', $booking), ['reason' => 'Dates are no longer available.'])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('declined', $booking->status);
        $this->assertNull(BookingConflictService::find(
            $this->property->id,
            $booking->check_in_date,
            $booking->check_out_date,
        ));
        $night = $this->property->availabilities()->whereDate('date', $booking->check_in_date)->firstOrFail();
        $this->assertTrue($night->is_available);
    }

    public function test_card_payment_confirmation_is_idempotent(): void
    {
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(38)->toDateString(),
            now('America/Los_Angeles')->addDays(40)->toDateString(),
        );
        $booking->update(['payment_method' => 'card']);
        $charge = new Charge(['type' => Charge::TYPE_ACCOMMODATION, 'status' => Charge::STATUS_SUCCESS]);
        $charge->setRelation('booking', $booking);
        $bookings = app(DirectBookingService::class);

        $bookings->confirmCardPayment($charge);
        $bookings->confirmCardPayment($charge);

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertNotNull($booking->payment_confirmed_at);
        $this->assertNull($booking->hold_expires_at);
        Mail::assertSent(DirectBookingMail::class, 3);
    }

    public function test_card_payment_returns_to_success_page_instead_of_opening_guest_portal(): void
    {
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(39)->toDateString(),
            now('America/Los_Angeles')->addDays(41)->toDateString(),
        );
        $booking->update([
            'payment_method' => 'card',
            'status' => 'pending',
            'payment_confirmed_at' => now(),
            'hold_expires_at' => null,
        ]);
        $intentId = 'pi_direct_booking_success';
        $charge = Charge::create([
            'booking_id' => $booking->id,
            'type' => Charge::TYPE_ACCOMMODATION,
            'amount_cents' => $booking->total_cents,
            'status' => Charge::STATUS_SUCCESS,
            'stripe_payment_intent_id' => $intentId,
        ]);
        $payments = \Mockery::mock(PaymentService::class);
        $payments->shouldReceive('finalize')->once()->with($intentId)->andReturn($charge);
        $this->app->instance(PaymentService::class, $payments);

        $this->postJson(route('public.checkout.confirm', [$booking->booking_id, $booking->token]), [
            'payment_intent_id' => $intentId,
        ])->assertOk()
            ->assertJsonPath('redirect', route('public.checkout.pay', [$booking->booking_id, $booking->token]));

        $this->get(route('public.checkout.pay', [$booking->booking_id, $booking->token]))
            ->assertOk()
            ->assertSee('Your stay is confirmed')
            ->assertSee('Open guest portal');
    }

    public function test_manual_payment_confirmation_completes_the_booking(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole('owner');
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(42)->toDateString(),
            now('America/Los_Angeles')->addDays(44)->toDateString(),
        );

        $this->actingAs($this->owner)
            ->get(route('admin.guests.show', $booking))
            ->assertOk()
            ->assertSee('Waiting for the guest to confirm payment and upload their receipt.')
            ->assertDontSee('Mark payment received');

        $this->get(route('public.checkout.pay', [$booking->booking_id, $booking->token]))
            ->assertOk()
            ->assertSee('Already sent your payment?')
            ->assertSee('I sent my payment')
            ->assertSee('name="receipt"', false)
            ->assertDontSee('name="confirmation"', false);

        $this->actingAs($this->owner)
            ->post(route('admin.guests.direct-payment-received', $booking), [
                'receipt' => UploadedFile::fake()->image('host-receipt.png'),
            ])
            ->assertSessionHasErrors('receipt');
        $this->assertNull($booking->fresh()->payment_confirmed_at);

        $this->post(route('public.checkout.payment-report', [$booking->booking_id, $booking->token]))
            ->assertSessionHasErrors('receipt');
        $this->assertDatabaseCount('payment_receipts', 0);

        $this->post(route('public.checkout.payment-report', [$booking->booking_id, $booking->token]), [
            'receipt' => UploadedFile::fake()->image('guest-receipt.png'),
        ])->assertRedirect(route('public.checkout.pay', [$booking->booking_id, $booking->token]));
        $this->assertDatabaseHas('payment_receipts', ['payable_id' => $booking->id, 'role' => 'guest']);
        $this->get(route('public.checkout.pay', [$booking->booking_id, $booking->token]))
            ->assertOk()
            ->assertSee('Payment confirmation in progress.')
            ->assertSee("We'll let you know when your payment is confirmed.")
            ->assertSee('max-height:84vh')
            ->assertDontSee('name="receipt"', false);

        $this->actingAs($this->owner)
            ->get(route('admin.guests.show', $booking))
            ->assertOk()
            ->assertSee('Your received receipt')
            ->assertSee('Mark payment received')
            ->assertSee('border-slate-300');

        $this->actingAs($this->owner)
            ->post(route('admin.guests.direct-payment-received', $booking), [
                'receipt' => UploadedFile::fake()->image('host-receipt.png'),
            ])
            ->assertRedirect();

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertNotNull($booking->payment_confirmed_at);
        $this->assertNull($booking->hold_expires_at);
        $this->assertDatabaseHas('payment_receipts', ['payable_id' => $booking->id, 'role' => 'host']);
        $this->assertDatabaseCount('cleaning_sessions', 1);
        $this->actingAs($this->owner)
            ->get(route('admin.guests.show', $booking))
            ->assertOk()
            ->assertSee('<dialog', false)
            ->assertSee('object-contain');
        $hostReceipt = PaymentReceipt::query()->where('payable_id', $booking->id)->where('role', 'host')->firstOrFail();
        $this->get(route('admin.guests.payment-receipt', [$booking, $hostReceipt]))->assertOk();
    }

    public function test_stripe_onboarding_starts_standard_account_oauth_with_session_state(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner->assignRole('owner');
        config([
            'services.stripe.secret' => 'sk_test_placeholder',
            'services.stripe.client_id' => 'ca_test_placeholder',
        ]);

        $response = $this->actingAs($this->owner)->post(route('admin.connect.onboard'));
        $response->assertRedirect();
        $query = [];
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('https://connect.stripe.com/oauth/authorize', strtok($response->headers->get('Location'), '?'));
        $this->assertSame('ca_test_placeholder', $query['client_id']);
        $this->assertSame('read_write', $query['scope']);
        $this->assertSame(route('admin.connect.return'), $query['redirect_uri']);
        $this->assertSame($this->app['session.store']->get('stripe_connect_state'), $query['state']);
    }

    public function test_guest_cancellation_refund_tiers_use_listing_policy(): void
    {
        $policy = app(DirectBookingCancellationPolicy::class);
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(10)->toDateString(),
            now('America/Los_Angeles')->addDays(12)->toDateString(),
        );
        $charge = new Charge(['amount_cents' => 10000, 'refunded_cents' => 0]);
        $booking->setRelation('property', $this->property);

        $this->listing->update(['cancellation_policy' => 'flexible']);
        $this->assertSame(10000, $policy->refundableCents($booking, $charge));

        $this->listing->update(['cancellation_policy' => 'moderate']);
        $this->assertSame(10000, $policy->refundableCents($booking, $charge));
        $booking->setAttribute('check_in_date', now('America/Los_Angeles')->addDays(4)->toDateString());
        $this->assertSame(0, $policy->refundableCents($booking, $charge));

        $this->listing->update(['cancellation_policy' => 'strict']);
        $booking->setAttribute('check_in_date', now('America/Los_Angeles')->addDays(8)->toDateString());
        $this->assertSame(5000, $policy->refundableCents($booking, $charge));
        $booking->setAttribute('check_in_date', now('America/Los_Angeles')->addDays(6)->toDateString());
        $this->assertSame(0, $policy->refundableCents($booking, $charge));
    }

    public function test_guest_can_cancel_an_unpaid_manual_hold_without_refund_and_dates_open_again(): void
    {
        $booking = $this->createDirectBooking(
            now('America/Los_Angeles')->addDays(40)->toDateString(),
            now('America/Los_Angeles')->addDays(42)->toDateString(),
        );

        $this->post(route('public.checkout.cancel', [$booking->booking_id, $booking->token]), [
            'confirmation' => '1',
        ])->assertRedirect(route('public.checkout.pay', [$booking->booking_id, $booking->token]));

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertTrue($booking->cancelled_by_guest);
        $this->assertNull(BookingConflictService::find(
            $this->property->id,
            $booking->check_in_date,
            $booking->check_out_date,
        ));
    }

    private function createDirectBooking(string $checkIn, string $checkOut): Booking
    {
        $response = $this->post(route('public.checkout.start', $this->listing->listing_slug), $this->manualCheckoutPayload($checkIn, $checkOut));
        $response->assertRedirect();

        return Booking::query()->latest('id')->firstOrFail();
    }

    private function manualCheckoutPayload(string $checkIn, string $checkOut): array
    {
        return [
            'guest_name' => 'Taylor Guest',
            'email' => 'taylor@example.test',
            'phone' => '5551234567',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guests' => 2,
            'payment_method' => 'manual',
            'host_payment_method_id' => $this->manualMethod->id,
            'terms' => '1',
        ];
    }
}
