<?php

namespace Tests\Feature;

use App\Mail\DirectBookingMail;
use App\Models\Charge;
use App\Models\Feature;
use App\Models\HostPaymentMethod;
use App\Models\LeaseApplication;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\Setting;
use App\Models\User;
use App\Services\LeaseApplicationIdScanService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaseApplicationTest extends TestCase
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
        foreach ([
            'direct_booking_site' => 'Direct booking site',
            'lease_applications' => 'Lease applications',
            'manual_payments' => 'Manual payments',
        ] as $key => $name) {
            Feature::updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'is_free' => true, 'active' => true],
            );
        }

        $this->owner = User::factory()->create(['host_name' => 'Coastal Host']);
        Role::findOrCreate('owner');
        $this->owner->assignRole('owner');
        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
            'active' => true,
        ]);
        $this->listing = PropertyListing::create([
            'property_id' => $this->property->id,
            'listing_slug' => 'coastal-lease',
            'public_title' => 'Coastal Lease',
            'public_description' => 'A long-term stay.',
            'max_guests' => 4,
            'base_rate_cents' => 12000,
            'booking_mode' => 'instant',
            'advance_notice_hours' => 0,
            'min_nights' => 1,
            'lease_enabled' => true,
            'lease_application_fee_cents' => 5000,
            'screening_provider_url' => 'https://screening.example.test/apply',
            'published_at' => now(),
        ]);
        $this->manualMethod = HostPaymentMethod::create([
            'owner_id' => $this->owner->id,
            'type' => 'venmo',
            'label' => 'Venmo',
            'handle' => '@coastal-host',
            'instructions' => 'Use your application reference.',
            'enabled' => true,
        ]);
    }

    public function test_public_four_step_application_keeps_ocr_values_locked_and_manual_fee_is_host_confirmed(): void
    {
        $this->useScan(['name' => 'Jordan Applicant', 'address' => '12 Ocean Road', 'number_last4' => '4567']);
        $this->get(route('public.listing.show', $this->listing->listing_slug))
            ->assertOk()
            ->assertSee(route('public.lease-application.apply', $this->listing->listing_slug));
        $this->get(route('public.lease-application.apply', $this->listing->listing_slug))
            ->assertOk()
            ->assertSee('Step 1 of 4');

        $this->post(route('public.lease-application.id', $this->listing->listing_slug), [
            'id_scan' => UploadedFile::fake()->image('license.jpg'),
            'id_name' => 'Edited OCR value',
        ])->assertSessionHasErrors('id_name');
        $this->assertDatabaseCount('lease_applications', 0);

        $this->post(route('public.lease-application.id', $this->listing->listing_slug), [
            'id_scan' => UploadedFile::fake()->image('license.jpg'),
        ])->assertRedirect();
        $application = LeaseApplication::query()->firstOrFail();
        $this->assertSame('scanned', $application->id_scan_status);
        $this->assertSame('Jordan Applicant', $application->id_name);
        $this->assertSame('12 Ocean Road', $application->id_address);
        $this->assertSame('4567', $application->id_number_last4);
        $this->assertArrayNotHasKey('id_number_last4', $application->toArray());
        $this->assertArrayNotHasKey('token', $application->toArray());
        $this->assertTrue(Storage::disk('local')->exists($application->id_scan_path));
        $this->assertNotSame('Jordan Applicant', DB::table('lease_applications')->where('id', $application->id)->value('id_name'));

        $about = [
            'applicant_name' => 'Jordan Applicant',
            'applicant_email' => 'jordan@example.test',
            'applicant_phone' => '8012345678',
            'phone_country_code' => '+234',
            'move_in_date' => now()->addMonth()->toDateString(),
            'lease_term_months' => 12,
            'occupants' => 2,
            'pets' => 'One cat',
            'vehicles' => 'One car',
            'current_address' => '7 Current Street',
            'current_address_months' => 18,
            'id_address' => 'Edited address',
            'ssn' => '123-45-6789',
        ];
        $this->post(route('public.lease-application.about.store', [$application->id, $application->token]), $about)
            ->assertSessionHasErrors(['id_address', 'ssn']);
        $this->assertSame('12 Ocean Road', $application->fresh()->id_address);

        unset($about['id_address']);
        unset($about['ssn']);
        $this->post(route('public.lease-application.about.store', [$application->id, $application->token]), $about)
            ->assertRedirect(route('public.lease-application.employment', [$application->id, $application->token]));
        $this->assertSame('7 Current Street', $application->fresh()->current_address);
        $this->assertSame('+2348012345678', $application->fresh()->applicant_phone);

        $this->post(route('public.lease-application.employment.store', [$application->id, $application->token]), [
            'employer' => 'Example Works',
            'job_title' => 'Designer',
            'supervisor_name' => 'Sam Supervisor',
            'supervisor_phone' => '5557654321',
            'monthly_income' => '6500.00',
            'years_employed' => '4.5',
            'previous_landlord_name' => 'Alex Landlord',
            'previous_landlord_phone' => '5551112222',
            'emergency_contact_name' => 'Casey Contact',
            'emergency_contact_phone' => '5552223333',
        ])->assertRedirect(route('public.lease-application.consent', [$application->id, $application->token]));
        $application->refresh();
        $this->assertSame(650000, (int) $application->monthly_income_cents);
        $this->assertNotSame('Example Works', DB::table('lease_applications')->where('id', $application->id)->value('employer'));
        $this->assertNotSame('650000', DB::table('lease_applications')->where('id', $application->id)->value('monthly_income_cents'));
        $this->assertFalse(in_array('date_of_birth', Schema::getColumnListing('lease_applications'), true));
        $this->assertFalse(in_array('ssn', Schema::getColumnListing('lease_applications'), true));

        $payload = [
            'screening_consent' => '1',
            'signature' => 'Wrong Name',
            'payment_method' => 'manual',
            'host_payment_method_id' => $this->manualMethod->id,
        ];
        $this->post(route('public.lease-application.submit', [$application->id, $application->token]), $payload)
            ->assertSessionHasErrors('signature');
        $this->assertSame('draft', $application->fresh()->status);
        $payload['signature'] = 'Jordan Applicant';
        $this->post(route('public.lease-application.submit', [$application->id, $application->token]), $payload)
            ->assertRedirect(route('public.lease-application.fee', [$application->id, $application->token]));

        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('pending', $application->fee_status);
        $this->assertSame(5000, $application->total_fee_cents);
        $this->assertSame(0, $application->processing_fee_cents);
        Mail::assertSent(DirectBookingMail::class, 2);
        $this->get(route('public.lease-application.fee', [$application->id, $application->token]))
            ->assertOk()
            ->assertSee('Use your application reference.');

        $this->actingAs($this->owner)
            ->post(route('admin.lease-applications.fee-received', $application))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('paid', $application->fresh()->fee_status);
        $this->put(route('admin.lease-applications.update', $application), [
            'status' => 'in_review',
            'host_notes' => 'Follow up with applicant.',
        ])->assertRedirect();
        $this->assertSame('in_review', $application->fresh()->status);
        $this->assertSame('Follow up with applicant.', $application->fresh()->host_notes);
        $this->post(route('admin.lease-applications.screening', $application))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNotNull($application->fresh()->screening_requested_at);
        Mail::assertSent(DirectBookingMail::class, 5);
    }

    public function test_failed_scan_allows_one_retry_then_flags_manual_review(): void
    {
        $this->useScan(['name' => null, 'address' => null, 'number_last4' => null]);
        $this->post(route('public.lease-application.id', $this->listing->listing_slug), [
            'id_scan' => UploadedFile::fake()->image('first.jpg'),
        ])->assertRedirect();
        $application = LeaseApplication::query()->firstOrFail();
        $this->assertSame('retry', $application->id_scan_status);
        $this->assertSame(1, $application->id_scan_attempts);

        $this->get(route('public.lease-application.id.retry', [$application->id, $application->token]))
            ->assertOk()
            ->assertSee('Retry scan');
        $this->post(route('public.lease-application.id.retry.store', [$application->id, $application->token]), [
            'id_scan' => UploadedFile::fake()->image('second.jpg'),
        ])->assertRedirect(route('public.lease-application.about', [$application->id, $application->token]));
        $this->assertSame('manual_review', $application->fresh()->id_scan_status);
        $this->assertSame(2, $application->fresh()->id_scan_attempts);
        $this->get(route('public.lease-application.id.retry', [$application->id, $application->token]))
            ->assertStatus(409);
    }

    public function test_about_phone_uses_the_selected_country_code(): void
    {
        $application = $this->readyDraft();

        $this->get(route('public.lease-application.about', [$application->id, $application->token]))
            ->assertOk()
            ->assertSee('guest-phone-country-button');

        $this->post(route('public.lease-application.about.store', [$application->id, $application->token]), [
            'applicant_name' => 'Jordan Applicant',
            'applicant_email' => 'jordan@example.test',
            'applicant_phone' => '8012345678',
            'phone_country_code' => '+234',
            'move_in_date' => now()->addMonth()->toDateString(),
            'lease_term_months' => 12,
            'occupants' => 2,
            'current_address' => '7 Current Street',
        ])->assertRedirect(route('public.lease-application.employment', [$application->id, $application->token]));

        $this->assertSame('+2348012345678', $application->fresh()->applicant_phone);
        $this->get(route('public.lease-application.about', [$application->id, $application->token]))
            ->assertOk()
            ->assertSee('manual host review')
            ->assertSee('guest-phone-country-button');
    }

    public function test_card_fee_includes_processing_fee_and_creates_connected_account_charge(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.key' => 'pk_test_fake',
        ]);
        $this->owner->forceFill([
            'stripe_account_id' => 'acct_connected',
            'stripe_charges_enabled' => true,
        ])->save();
        Setting::putValue('processing_fee_percent', '3');
        $application = $this->readyDraft();

        $payments = \Mockery::mock(PaymentService::class);
        $payments->shouldReceive('isConfigured')->andReturn(true);
        $payments->shouldReceive('createLeaseApplicationIntent')
            ->once()
            ->withArgs(fn (LeaseApplication $record, string $account) => $record->id === $application->id && $account === 'acct_connected')
            ->andReturnUsing(function (LeaseApplication $record, string $account): array {
                $charge = $record->charges()->create([
                    'type' => Charge::TYPE_APPLICATION_FEE,
                    'amount_cents' => $record->total_fee_cents,
                    'status' => Charge::STATUS_PENDING,
                    'stripe_payment_intent_id' => 'pi_lease_test',
                    'stripe_account_id' => $account,
                ]);

                return ['client_secret' => 'pi_secret', 'charge' => $charge];
            });
        $this->app->instance(PaymentService::class, $payments);

        $this->post(route('public.lease-application.submit', [$application->id, $application->token]), [
            'screening_consent' => '1',
            'signature' => 'Jordan Applicant',
            'payment_method' => 'card',
        ])->assertRedirect(route('public.lease-application.fee', [$application->id, $application->token]));

        $application->refresh();
        $this->assertSame(5000, $application->application_fee_cents);
        $this->assertSame(150, $application->processing_fee_cents);
        $this->assertSame(5150, $application->total_fee_cents);
        $this->assertSame(5150, $application->charges()->firstOrFail()->amount_cents);
        $this->assertSame('acct_connected', $application->charges()->firstOrFail()->stripe_account_id);

        $application->update(['fee_status' => 'failed']);
        $application->charges()->firstOrFail()->update(['status' => Charge::STATUS_FAILED]);
        $this->post(route('public.lease-application.retry-fee', [$application->id, $application->token]))
            ->assertRedirect(route('public.lease-application.fee', [$application->id, $application->token]));
        $this->assertSame('pending', $application->fresh()->fee_status);
    }

    public function test_only_listing_owner_and_admin_can_view_applications_and_id_images(): void
    {
        $application = $this->readyDraft();
        $application->update(['status' => 'submitted', 'submitted_at' => now()]);
        Storage::disk('local')->put($application->id_scan_path, 'private image');
        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('owner');

        $this->actingAs($otherOwner)
            ->get(route('admin.lease-applications.show', $application))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('admin.lease-applications.show', $application))
            ->assertOk()
            ->assertSee('Jordan Applicant');
        $idResponse = $this->get(route('admin.lease-applications.id', $application))->assertOk();
        $this->assertStringContainsString('private', $idResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $idResponse->headers->get('Cache-Control'));

        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)
            ->get(route('admin.lease-applications.show', $application))
            ->assertOk();
    }

    public function test_honeypot_and_throttle_protect_id_upload_endpoint(): void
    {
        $this->useScan(['name' => null, 'address' => null, 'number_last4' => null]);
        $this->post(route('public.lease-application.id', $this->listing->listing_slug), [
            'website' => 'bot',
            'id_scan' => UploadedFile::fake()->image('bot.jpg'),
        ])->assertSessionHasErrors('website');
        $this->assertDatabaseCount('lease_applications', 0);

        for ($attempt = 0; $attempt < 9; $attempt++) {
            $response = $this->post(route('public.lease-application.id', $this->listing->listing_slug), [
                'website' => 'bot',
                'id_scan' => UploadedFile::fake()->image("bot-{$attempt}.jpg"),
            ]);
            $response->assertSessionHasErrors('website');
        }
        $this->post(route('public.lease-application.id', $this->listing->listing_slug), [
            'website' => 'bot',
            'id_scan' => UploadedFile::fake()->image('bot-last.jpg'),
        ])->assertStatus(429);
    }

    public function test_purge_removes_expired_declined_records_and_private_id_files(): void
    {
        $application = $this->readyDraft();
        $path = $application->id_scan_path;
        Storage::disk('local')->put($path, 'private image');
        $application->update([
            'status' => 'declined',
            'declined_at' => now()->subDays(91),
        ]);
        $recent = $this->readyDraft();
        $recent->update([
            'status' => 'declined',
            'declined_at' => now()->subDays(10),
        ]);

        $this->artisan('lease-applications:purge')->assertSuccessful();

        $this->assertDatabaseMissing('lease_applications', ['id' => $application->id]);
        $this->assertDatabaseHas('lease_applications', ['id' => $recent->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    private function useScan(array $result): void
    {
        $scanner = \Mockery::mock(LeaseApplicationIdScanService::class);
        $scanner->shouldReceive('scan')->andReturn($result);
        $this->app->instance(LeaseApplicationIdScanService::class, $scanner);
    }

    private function readyDraft(): LeaseApplication
    {
        Storage::disk('local')->put('lease-applications/id.jpg', 'private image');

        return LeaseApplication::create([
            'listing_id' => $this->listing->id,
            'property_id' => $this->property->id,
            'status' => 'draft',
            'token' => str_repeat('t', 64),
            'id_name' => 'Jordan Applicant',
            'id_address' => '12 Ocean Road',
            'id_number_last4' => '4567',
            'id_scan_status' => 'scanned',
            'id_scan_attempts' => 1,
            'id_scan_path' => 'lease-applications/id.jpg',
            'applicant_name' => 'Jordan Applicant',
            'applicant_email' => 'jordan@example.test',
            'applicant_phone' => '5551234567',
            'employer' => 'Example Works',
            'job_title' => 'Designer',
            'monthly_income_cents' => 650000,
        ]);
    }
}
