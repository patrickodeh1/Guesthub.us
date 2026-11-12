<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Feature;
use App\Models\LeaseApplication;
use App\Models\PropertyListing;
use App\Services\FeatureGate;
use App\Services\LeaseApplicationIdScanService;
use App\Services\LeaseApplicationNotificationService;
use App\Services\Payments\PaymentService;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaseApplicationController extends Controller
{
    public function apply(Request $request, string $listingSlug)
    {
        $sessionKey = 'lease_prefill_'.$listingSlug;
        $prefill = $request->session()->get($sessionKey);
        if (! is_array($prefill) && is_array($request->session()->get('lease_prefill'))) {
            $prefill = $request->session()->pull('lease_prefill');
            $request->session()->put($sessionKey, $prefill);
        }
        if ($request->filled('check_in') && $request->filled('check_out')) {
            $prefill = ['check_in' => $request->query('check_in'), 'check_out' => $request->query('check_out'), 'guests' => $request->query('guests', 1)];
            $request->session()->put($sessionKey, $prefill);
        } elseif (is_array($prefill)) {
            $request->session()->put($sessionKey, $prefill);
        }
        return view('public.lease-application-id', [
            'listing' => $this->publishedLeaseListing($listingSlug),
            'application' => null,
            'retry' => false,
        ]);
    }

    public function storeId(
        Request $request,
        string $listingSlug,
        LeaseApplicationIdScanService $scanner,
    ) {
        $listing = $this->publishedLeaseListing($listingSlug);
        $this->validateScanRequest($request);

        return $this->scanAndContinue($request, $listing, null, $scanner);
    }

    public function retryId(
        Request $request,
        LeaseApplication $application,
        string $token,
        LeaseApplicationIdScanService $scanner,
    ) {
        $this->assertDraft($application, $token);
        abort_unless($application->id_scan_attempts < 2, 409, 'The ID scan retry limit has been reached.');
        $this->validateScanRequest($request);

        return $this->scanAndContinue($request, $application->listing, $application, $scanner);
    }

    public function retryIdForm(LeaseApplication $application, string $token)
    {
        $application = $this->assertDraft($application, $token);
        abort_unless($application->id_scan_attempts < 2, 409);

        return view('public.lease-application-id', [
            'listing' => $application->listing,
            'application' => $application,
            'retry' => true,
        ]);
    }

    public function about(LeaseApplication $application, string $token)
    {
        $application = $this->assertDraft($application, $token);

        return view('public.lease-application-about', compact('application'));
    }

    public function saveAbout(Request $request, LeaseApplication $application, string $token)
    {
        $application = $this->assertDraft($application, $token);
        $this->rejectProtectedFields($request);
        $data = $request->validate([
            'website' => ['nullable', 'size:0'],
            'applicant_name' => ['required', 'string', 'max:255'],
            'applicant_email' => ['required', 'email:rfc', 'max:255'],
            'applicant_phone' => ['required', 'string', 'max:40'],
            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'move_in_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'requested_end_date' => ['nullable', 'date_format:Y-m-d', 'after:move_in_date'],
            'lease_term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'occupants' => ['required', 'integer', 'min:1', 'max:30'],
            'pets' => ['nullable', 'string', 'max:1000'],
            'vehicles' => ['nullable', 'string', 'max:1000'],
            'current_address' => ['required', 'string', 'max:1000'],
            'current_address_months' => ['nullable', 'integer', 'min:0', 'max:1200'],
        ]);
        $this->rejectHoneypot($request);
        $data['applicant_phone'] = \App\Support\PhoneFormatter::normalizeForStorage(
            $data['applicant_phone'],
            $data['phone_country_code'] ?? '+1',
        );

        $application->update([
            'applicant_name' => $data['applicant_name'],
            'applicant_email' => $data['applicant_email'],
            'applicant_phone' => $data['applicant_phone'],
            'move_in_date' => $data['move_in_date'],
            'requested_end_date' => $data['requested_end_date'] ?? null,
            'lease_term_months' => $data['lease_term_months'],
            'occupants' => $data['occupants'],
            'pets' => $data['pets'] ?? null,
            'vehicles' => $data['vehicles'] ?? null,
            'current_address' => $data['current_address'],
            'current_address_months' => $data['current_address_months'],
        ]);

        return redirect()->route('public.lease-application.employment', [$application->id, $application->token]);
    }

    public function employment(LeaseApplication $application, string $token)
    {
        $application = $this->assertDraft($application, $token);
        abort_unless(filled($application->applicant_name), 409);

        return view('public.lease-application-employment', compact('application'));
    }

    public function saveEmployment(Request $request, LeaseApplication $application, string $token)
    {
        $application = $this->assertDraft($application, $token);
        $this->rejectProtectedFields($request);
        $data = $request->validate([
            'website' => ['nullable', 'size:0'],
            'employer' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'supervisor_name' => ['nullable', 'string', 'max:255'],
            'supervisor_phone' => ['nullable', 'string', 'max:40'],
            'monthly_income' => ['required', 'string', 'max:11', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
            'years_employed' => ['required', 'numeric', 'min:0', 'max:100'],
            'previous_landlord_name' => ['nullable', 'string', 'max:255'],
            'previous_landlord_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:40'],
        ]);
        $this->rejectHoneypot($request);

        $application->update([
            'employer' => $data['employer'],
            'job_title' => $data['job_title'],
            'supervisor_name' => $data['supervisor_name'] ?? null,
            'supervisor_phone' => $data['supervisor_phone'] ?? null,
            'monthly_income_cents' => $this->dollarsToCents($data['monthly_income']),
            'years_employed' => (string) $data['years_employed'],
            'previous_landlord_name' => $data['previous_landlord_name'] ?? null,
            'previous_landlord_phone' => $data['previous_landlord_phone'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ]);

        return redirect()->route('public.lease-application.consent', [$application->id, $application->token]);
    }

    public function consent(LeaseApplication $application, string $token)
    {
        $application = $this->assertPublicApplication($application, $token);
        if ($application->status !== 'draft') {
            return redirect()->route('public.lease-application.fee', [$application->id, $application->token]);
        }
        $application = $this->assertDraft($application, $token);
        abort_unless(filled($application->employer), 409);
        $owner = $application->property->owner;
        $canCard = filled($owner?->stripe_account_id)
            && $owner?->stripe_charges_enabled
            && app(PaymentService::class)->isConfigured();
        $manualMethods = $owner && FeatureGate::enabled($owner, 'manual_payments')
            ? $owner->hostPaymentMethods()->where('enabled', true)->orderBy('id')->get()
            : collect();
        abort_if(! $canCard && $manualMethods->isEmpty() && $application->listing->lease_application_fee_cents > 0, 404);

        return view('public.lease-application-consent', [
            'application' => $application,
            'canCard' => $canCard,
            'manualMethods' => $manualMethods,
        ]);
    }

    public function submit(
        Request $request,
        LeaseApplication $application,
        string $token,
        PaymentService $payments,
        QuoteService $quotes,
        LeaseApplicationNotificationService $notifications,
    ) {
        $application = $this->assertDraft($application, $token);
        $this->rejectProtectedFields($request);
        $data = $request->validate([
            'website' => ['nullable', 'size:0'],
            'screening_consent' => ['accepted'],
            'sms_consent' => ['nullable', 'boolean'],
            'signature' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(['card', 'manual'])],
            'host_payment_method_id' => ['required_if:payment_method,manual', 'nullable', 'integer'],
        ]);
        $this->rejectHoneypot($request);

        if (mb_strtolower(trim($data['signature'])) !== mb_strtolower(trim((string) $application->applicant_name))) {
            throw ValidationException::withMessages([
                'signature' => 'Type your full name exactly as entered on the application.',
            ]);
        }

        $owner = $application->property->owner;
        $feeCents = (int) ($application->listing->lease_application_fee_cents ?? 0);
        if ($feeCents > 0) {
            if (! $request->boolean('fee_policy_ack')) {
                throw ValidationException::withMessages(['fee_policy_ack' => 'Please confirm that you understand the application fee policy.']);
            }
            $application->forceFill(['fee_policy_accepted_at' => now()])->save();
        }
        $method = $feeCents > 0 ? $data['payment_method'] : 'none';
        $hostPaymentMethod = null;
        $processingCents = 0;
        if ($method === 'card') {
            abort_unless(filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && $payments->isConfigured(), 422, 'Card payment is not available.');
            $processingCents = $quotes->cardProcessingFeeCents($feeCents);
        } elseif ($method === 'manual') {
            abort_unless($owner && FeatureGate::enabled($owner, 'manual_payments'), 422, 'Manual payment is not available.');
            $hostPaymentMethod = $owner->hostPaymentMethods()
                ->whereKey($data['host_payment_method_id'] ?? 0)
                ->where('enabled', true)
                ->first();
            abort_unless($hostPaymentMethod, 422, 'Choose an enabled host payment method.');
        }

        $totalFeeCents = $feeCents + $processingCents;
        DB::transaction(function () use ($request, $application, $token, $data, $method, $hostPaymentMethod, $feeCents, $processingCents, $totalFeeCents): void {
            $lockedApplication = LeaseApplication::query()->lockForUpdate()->findOrFail($application->id);
            $this->assertDraft($lockedApplication, $token);
            $lockedApplication->update([
                'status' => 'submitted',
                'fee_status' => $totalFeeCents > 0 ? 'pending' : 'paid',
                'payment_method' => $method,
                'host_payment_method_id' => $hostPaymentMethod?->id,
                'application_fee_cents' => $feeCents,
                'processing_fee_cents' => $processingCents,
                'total_fee_cents' => $totalFeeCents,
                'consent_at' => now(),
                'consent_signature' => trim($data['signature']),
                'consent_ip' => $request->ip(),
                'consent_user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'submitted_at' => now(),
            ]);
        });

        $application->refresh();
        if ($request->boolean('sms_consent')) {
            \App\Services\SmsConsentService::recordLeaseOptIn($application, [
                'disclosure_text' => 'Text me updates about this application and my lease. Msg and data rates may apply. Message frequency varies. Reply STOP to cancel, HELP for help.',
                'opt_in_method' => 'lease_application',
                'page_url' => route('public.lease-application.apply', $application->listing->listing_slug),
            ]);
        }
        $notifications->submitted($application);
        if ($method === 'card' && $application->total_fee_cents > 0) {
            $payments->createLeaseApplicationIntent($application, $owner->stripe_account_id);
        }

        return redirect()->route('public.lease-application.fee', [$application->id, $application->token]);
    }

    public function fee(
        LeaseApplication $application,
        string $token,
        PaymentService $payments,
    ) {
        $application = $this->assertPublicApplication($application, $token);
        $application->load(['listing', 'property.owner', 'hostPaymentMethod']);
        $clientSecret = null;

        if ($application->fee_status === 'pending' && $application->payment_method === 'card') {
            $owner = $application->property->owner;
            abort_unless(filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && $payments->isConfigured(), 503, 'Card payment is temporarily unavailable.');
            $intent = $payments->createLeaseApplicationIntent($application, $owner->stripe_account_id);
            $clientSecret = $intent['client_secret'];
            $application->refresh();
            if ($application->fee_status !== 'pending') {
                $clientSecret = null;
            }
        }

        return view('public.lease-application-fee', [
            'application' => $application->fresh(['listing', 'property.owner', 'hostPaymentMethod']),
            'clientSecret' => $clientSecret,
            'publishableKey' => config('services.stripe.key'),
            'stripeAccountId' => $application->property->owner?->stripe_account_id,
        ]);
    }

    public function confirmFee(
        Request $request,
        LeaseApplication $application,
        string $token,
        PaymentService $payments,
    ) {
        $application = $this->assertPublicApplication($application, $token);
        $data = $request->validate(['payment_intent_id' => ['required', 'string', 'max:255']]);
        $charge = $application->charges()
            ->where('type', Charge::TYPE_APPLICATION_FEE)
            ->where('stripe_payment_intent_id', $data['payment_intent_id'])
            ->first();
        if (! $charge) {
            return response()->json(['ok' => false, 'error' => 'Payment not found.'], 404);
        }

        $charge = $payments->finalize($data['payment_intent_id']);
        if (! $charge || $charge->status !== Charge::STATUS_SUCCESS) {
            return response()->json(['ok' => false, 'error' => 'Payment could not be confirmed.'], 422);
        }

        return response()->json([
            'ok' => true,
            'redirect' => route('public.lease-application.fee', [$application->id, $application->token]),
        ]);
    }

    public function retryFee(
        LeaseApplication $application,
        string $token,
        PaymentService $payments,
    ) {
        $application = $this->assertPublicApplication($application, $token);
        abort_unless(
            $application->status === 'submitted'
                && $application->fee_status === 'failed'
                && $application->payment_method === 'card',
            409,
        );
        $owner = $application->property->owner;
        abort_unless(filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && $payments->isConfigured(), 503);
        $application->update(['fee_status' => 'pending']);

        return redirect()->route('public.lease-application.fee', [$application->id, $application->token]);
    }


    private function applyLeasePrefill(Request $request, LeaseApplication $application): void
    {
        $application->loadMissing('listing');
        $sessionKey = 'lease_prefill_'.($application->listing?->listing_slug ?? '');
        $prefill = $request->session()->get($sessionKey);
        if (! is_array($prefill) || empty($prefill['check_in']) || empty($prefill['check_out'])) return;
        try {
            $start = \Carbon\Carbon::parse($prefill['check_in'])->startOfDay();
            $end = \Carbon\Carbon::parse($prefill['check_out'])->startOfDay();
            $nights = $start->diffInDays($end);
            if ($nights < 29 || $end->lte($start)) return;
            $application->update([
                'move_in_date' => $start->toDateString(),
                'requested_end_date' => $end->toDateString(),
                'lease_term_months' => max(1, (int) ceil($nights / 30)),
                'occupants' => max(1, min(30, (int) ($prefill['guests'] ?? 1))),
            ]);
            $request->session()->forget($sessionKey);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function scanAndContinue(
        Request $request,
        PropertyListing $listing,
        ?LeaseApplication $application,
        LeaseApplicationIdScanService $scanner,
    ) {
        $path = $request->file('id_scan')->store('lease-applications', 'local');
        if (! $path) {
            throw new \RuntimeException('The uploaded ID scan could not be stored privately.');
        }

        $fields = $scanner->scan($path);
        $attempts = ($application?->id_scan_attempts ?? 0) + 1;
        $complete = filled($fields['name']) && filled($fields['address']) && filled($fields['number_last4']);
        $status = $complete ? 'scanned' : ($attempts >= 2 ? 'manual_review' : 'retry');
        $oldPath = $application?->id_scan_path;

        if (! $application) {
            $application = new LeaseApplication([
                'listing_id' => $listing->id,
                'property_id' => $listing->property_id,
                'token' => Str::random(64),
            ]);
        }

        $application->fill([
            'id_name' => $fields['name'],
            'id_address' => $fields['address'],
            'id_number_last4' => $fields['number_last4'],
            'id_scan_status' => $status,
            'id_scan_attempts' => $attempts,
            'id_scan_path' => $path,
        ])->save();
        $this->applyLeasePrefill(request(), $application);

        if ($oldPath && $oldPath !== $path && Storage::disk('local')->exists($oldPath)
            && ! Storage::disk('local')->delete($oldPath)) {
            throw new \RuntimeException('The previous private ID image could not be removed.');
        }

        if ($status === 'retry') {
            return redirect()->route('public.lease-application.id.retry', [$application->id, $application->token])
                ->with('status', 'We could not read all required ID fields. Upload a clearer image to retry once.');
        }

        return redirect()->route('public.lease-application.about', [$application->id, $application->token]);
    }

    public function storeIdPhotos(Request $request, string $listingSlug, LeaseApplicationIdScanService $scanner)
    {
        return $this->scanPhotos($request, $this->publishedLeaseListing($listingSlug), null, $scanner);
    }

    public function retryIdPhotos(Request $request, LeaseApplication $application, string $token, LeaseApplicationIdScanService $scanner)
    {
        $application = $this->assertDraft($application, $token);
        abort_unless($application->id_scan_attempts < 2, 409, 'The ID scan retry limit has been reached.');

        return $this->scanPhotos($request, $application->listing, $application, $scanner);
    }

    private function scanPhotos(Request $request, PropertyListing $listing, ?LeaseApplication $application, LeaseApplicationIdScanService $scanner)
    {
        $this->rejectProtectedFields($request);
        $isPassport = $request->input('id_type') === 'passport' && (bool) $listing->lease_allow_passport;
        $request->validate([
            'website' => ['nullable', 'size:0'],
            'id_type' => ['nullable', 'in:state_id,passport'],
            'photo_id' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'photo_id_back' => [$isPassport ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'id_barcode_raw' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->rejectHoneypot($request);

        $frontPath = $request->file('photo_id')->store('lease-applications', 'local');
        $backPath = $request->hasFile('photo_id_back') ? $request->file('photo_id_back')->store('lease-applications', 'local') : null;
        if (! $frontPath || ($request->hasFile('photo_id_back') && ! $backPath)) {
            throw new \RuntimeException('The uploaded ID images could not be stored privately.');
        }

        $fields = null;
        $barcodeRaw = $request->input('id_barcode_raw');
        if (! $isPassport && $backPath) {
            $decoded = app(\App\Services\BarcodeDecoder::class)->decodeStored($backPath);
            if (filled($decoded['text'] ?? null)) {
                $barcodeRaw = $decoded['text'];
            }
            if (filled($barcodeRaw)) {
                $parsed = \App\Services\AamvaParser::parse((string) $barcodeRaw);
                if ($parsed && filled($parsed['last_name'] ?? null) && filled($parsed['dob'] ?? null)) {
                    $fields = $parsed;
                }
            }
        }
        if (! $fields && $isPassport) {
            $text = app(\App\Services\IdDocumentExtractor::class)->readRawText($frontPath);
            $fields = $text ? \App\Services\MrzParser::parseFromText($text) : null;
        }

        if ($fields) {
            $name = trim(trim(($fields['first_name'] ?? '').' '.($fields['middle_name'] ?? '')).' '.($fields['last_name'] ?? ''));
            $number = preg_replace('/[^A-Z0-9]/i', '', (string) ($fields['id_number'] ?? ''));
            $read = [
                'name' => $name !== '' ? $name : null,
                'address' => $fields['address'] ?? null,
                'number_last4' => strlen($number) >= 4 ? strtoupper(substr($number, -4)) : null,
            ];
            $complete = filled($read['name']);
        } else {
            $read = $scanner->scan($frontPath);
            $complete = filled($read['name']) && filled($read['address']) && filled($read['number_last4']);
        }

        $attempts = ($application?->id_scan_attempts ?? 0) + 1;
        $status = $complete ? 'scanned' : ($attempts >= 2 ? 'manual_review' : 'retry');
        $oldPaths = array_filter([$application?->id_scan_path, $application?->id_back_scan_path]);

        if (! $application) {
            $application = new LeaseApplication([
                'listing_id' => $listing->id,
                'property_id' => $listing->property_id,
                'token' => Str::random(64),
            ]);
        }
        $application->fill([
            'id_type' => $isPassport ? 'passport' : 'state_id',
            'id_name' => $read['name'],
            'id_address' => $read['address'],
            'id_number_last4' => $read['number_last4'],
            'id_scan_status' => $status,
            'id_scan_attempts' => $attempts,
            'id_scan_path' => $frontPath,
            'id_back_scan_path' => $backPath,
        ])->save();
        $this->applyLeasePrefill($request, $application);

        foreach ($oldPaths as $old) {
            if (! in_array($old, [$frontPath, $backPath], true) && Storage::disk('local')->exists($old)) {
                Storage::disk('local')->delete($old);
            }
        }

        $next = $status === 'retry'
            ? route('public.lease-application.id.retry', [$application->id, $application->token])
            : route('public.lease-application.about', [$application->id, $application->token]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $status !== 'retry',
                'status' => $status,
                'redirect' => $next,
                'message' => $status === 'retry' ? 'We could not read your ID. Please retake the photos and try once more.' : null,
            ]);
        }

        return redirect($next);
    }

    private function validateScanRequest(Request $request): void
    {
        $this->rejectProtectedFields($request);
        $request->validate([
            'website' => ['nullable', 'size:0'],
            'id_scan' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $this->rejectHoneypot($request);
    }

    private function rejectProtectedFields(Request $request): void
    {
        $request->validate([
            'id_name' => ['prohibited'],
            'id_address' => ['prohibited'],
            'id_number' => ['prohibited'],
            'id_number_last4' => ['prohibited'],
            'date_of_birth' => ['prohibited'],
            'dob' => ['prohibited'],
            'ssn' => ['prohibited'],
        ]);
    }

    private function rejectHoneypot(Request $request): void
    {
        if ($request->filled('website')) {
            throw ValidationException::withMessages(['application' => 'Unable to process this application.']);
        }
    }

    private function publishedLeaseListing(string $slug): PropertyListing
    {
        $listing = PropertyListing::query()
            ->where('listing_slug', $slug)
            ->whereNotNull('published_at')
            ->with(['property.owner'])
            ->firstOrFail();
        abort_unless($listing->lease_enabled && $listing->property?->active && $listing->property?->owner, 404);
        abort_unless(Feature::query()->where('key', 'direct_booking_site')->where('active', true)->exists(), 404);
        abort_unless(FeatureGate::enabled($listing->property->owner, 'direct_booking_site'), 404);
        abort_unless(FeatureGate::enabled($listing->property->owner, 'lease_applications'), 404);

        return $listing;
    }

    private function assertDraft(LeaseApplication $application, string $token): LeaseApplication
    {
        $application = $this->assertPublicApplication($application, $token);
        abort_unless(in_array($application->status, ['draft', 'submitted'], true) && $application->created_at?->gt(now()->subDay()), 410);

        return $application;
    }

    private function assertPublicApplication(LeaseApplication $application, string $token): LeaseApplication
    {
        abort_unless(filled($application->token) && hash_equals($application->token, $token), 404);

        return $application;
    }

    private function dollarsToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
