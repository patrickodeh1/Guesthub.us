<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\HostPaymentMethod;
use App\Models\LeaseApplication;
use App\Models\LeaseOffer;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaseOfferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_lease_offer_holds_dates_and_confirms_booking_after_both_signatures_and_initial_payments(): void
    {
        Mail::fake();
        Role::findOrCreate('owner');
        $owner = User::factory()->create(['host_name' => 'Coastal Host']);
        $owner->assignRole('owner');
        $property = Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        $listing = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'lease-offer-test',
            'public_title' => 'Lease Offer Test',
            'public_description' => 'Long-term home.',
            'max_guests' => 4,
            'base_rate_cents' => 10000,
            'booking_mode' => 'instant',
            'advance_notice_hours' => 0,
            'min_nights' => 1,
            'lease_enabled' => true,
            'published_at' => now(),
        ]);
        $method = HostPaymentMethod::create([
            'owner_id' => $owner->id,
            'type' => 'venmo',
            'label' => 'Venmo',
            'handle' => '@coastal-host',
            'instructions' => 'Include the application reference.',
            'enabled' => true,
        ]);
        $application = LeaseApplication::create([
            'listing_id' => $listing->id,
            'property_id' => $property->id,
            'status' => 'approved',
            'fee_status' => 'paid',
            'token' => str_repeat('a', 64),
            'applicant_name' => 'Jordan Applicant',
            'applicant_email' => 'jordan@example.test',
            'applicant_phone' => '+15551234567',
            'move_in_date' => now()->addMonth()->toDateString(),
            'lease_term_months' => 1,
            'occupants' => 2,
            'approved_at' => now(),
        ]);

        $start = now()->addMonth()->toDateString();
        $end = now()->addMonths(2)->toDateString();
        $this->actingAs($owner)->post(route('admin.lease-applications.offers.create', $application), [
            'start_date' => $start,
            'end_date' => $end,
            'term_months' => 1,
            'monthly_rent' => '1500.00',
            'deposit' => '500.00',
            'fees' => '50.00',
            'payment_method' => 'manual',
            'host_payment_method_id' => $method->id,
            'accept_by' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'contract_html' => '<h2>Lease terms</h2><p>Tenant: [[applicant_name]]</p>',
        ])->assertRedirect()->assertSessionHas('success');

        $offer = LeaseOffer::query()->firstOrFail()->load(['installments', 'booking', 'application']);
        $booking = $offer->booking;
        $this->assertSame(LeaseOffer::STATUS_SENT, $offer->status);
        $this->assertSame(Booking::STATUS_PENDING_PAYMENT, $booking->status);
        $this->assertSame('lease', $booking->source);
        $this->assertSame($application->id, (int) $booking->lease_application_id);
        $this->assertSame($offer->id, (int) $booking->lease_offer_id);
        $this->assertNotNull($booking->hold_expires_at);
        $this->get(route('public.lease-offer.show', [$application->id, $application->token, $offer->token]))
            ->assertOk()
            ->assertSee('Review your rental offer')
            ->assertSee('Amount required before confirmation: $2,050.00')
            ->assertSee('Rent · '.now()->addMonth()->format('F Y'));

        $this->post(route('public.lease-offer.sign', [$application->id, $application->token, $offer->token]), [
            'signature' => 'Jordan Applicant',
            'accept_terms' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $offer->refresh();
        $this->assertSame(LeaseOffer::STATUS_SIGNED, $offer->status);
        $this->assertNotNull($offer->host_signed_at);
        $this->assertNotNull($offer->guest_signed_at);
        $this->assertNull($booking->fresh()->payment_confirmed_at);
        $this->get(route('public.lease-offer.show', [$application->id, $application->token, $offer->token]))
            ->assertOk()
            ->assertSee('Tenant: Jordan Applicant')
            ->assertSee('Initial payment · Due now')
            ->assertSee('$2,050.00');

        $this->actingAs($owner)->get(route('admin.lease-applications.show', $application))
            ->assertOk()
            ->assertSee('Record full initial payment received')
            ->assertSee('$2,050.00');

        $this->actingAs($owner)->post(route('admin.lease-applications.offers.installments.received', [
            $application, $offer, $offer->installments()->where('required_at_signing', true)->firstOrFail(),
        ]))->assertStatus(409);

        $this->actingAs($owner)->post(route('admin.lease-applications.offers.initial-payment.received', [
            $application, $offer,
        ]))->assertRedirect()->assertSessionHas('success');


        $offer->refresh();
        $booking->refresh();
        $this->assertSame(LeaseOffer::STATUS_CONFIRMED, $offer->status);
        $this->assertSame('pending', $booking->status);
        $this->assertSame('lease', $booking->source);
        $this->assertSame('Lease', $booking->booking_platform);
        $this->assertNotNull($booking->payment_confirmed_at);
        $this->assertNull($booking->hold_expires_at);
        $this->assertStringContainsString('Jordan Applicant', $booking->contract_snapshot);
        $this->get(route('guest.rental-agreement', [$booking->booking_id, $booking->token]))
            ->assertOk()
            ->assertSee('Lease Agreement')
            ->assertSee('Lease terms')
            ->assertSee('Tenant: Jordan Applicant')
            ->assertDontSee('Government ID');
        $this->assertDatabaseHas('lease_installments', ['lease_offer_id' => $offer->id, 'kind' => 'deposit', 'status' => 'paid']);
        $this->assertSame(205000, (int) $offer->fresh()->initial_due_cents);
        $this->assertSame(205000, (int) $offer->installments()->where('required_at_signing', true)->sum('amount_cents'));
        $this->actingAs($owner)->get(route('admin.guests.show', $booking))
            ->assertOk()
            ->assertSee('Lease reservation')
            ->assertSee('Installment payments')
            ->assertSee('Paid')
            ->assertDontSee('Direct booking payment');
    }

    public function test_expired_offer_releases_its_pending_reservation(): void
    {
        Mail::fake();
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $property = Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        $listing = PropertyListing::create([
            'property_id' => $property->id, 'listing_slug' => 'lease-expiry-test',
            'public_title' => 'Lease Expiry Test', 'public_description' => 'Home.',
            'max_guests' => 2, 'base_rate_cents' => 10000, 'booking_mode' => 'instant',
            'advance_notice_hours' => 0, 'min_nights' => 1, 'lease_enabled' => true,
            'published_at' => now(),
        ]);
        $method = HostPaymentMethod::create(['owner_id' => $owner->id, 'type' => 'venmo', 'label' => 'Venmo', 'enabled' => true]);
        $application = LeaseApplication::create([
            'listing_id' => $listing->id, 'property_id' => $property->id,
            'status' => 'approved', 'fee_status' => 'paid', 'token' => str_repeat('b', 64),
            'applicant_name' => 'Casey Tenant', 'applicant_email' => 'casey@example.test',
            'move_in_date' => now()->addMonth()->toDateString(), 'lease_term_months' => 1,
            'occupants' => 1, 'approved_at' => now(),
        ]);
        $this->actingAs($owner)->post(route('admin.lease-applications.offers.create', $application), [
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'term_months' => 1, 'monthly_rent' => '1000.00', 'deposit' => '0.00',
            'fees' => '0.00', 'initial_due' => '1000.00', 'payment_method' => 'manual',
            'host_payment_method_id' => $method->id, 'accept_by' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'contract_html' => '<p>Lease for [[applicant_name]]</p>',
        ])->assertRedirect();
        $offer = LeaseOffer::query()->firstOrFail();
        $booking = Booking::findOrFail($offer->booking_id);
        $offer->update(['accept_by' => now()->subMinute()]);
        $this->artisan('lease-offers:expire')->assertSuccessful();
        $this->assertSame(LeaseOffer::STATUS_EXPIRED, $offer->fresh()->status);
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->hold_expires_at);
    }
}
