<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaseApplication;
use App\Models\LeaseOffer;
use App\Models\LeaseInstallment;
use App\Models\HostPaymentMethod;
use App\Services\LeaseOfferService;
use App\Mail\DirectBookingMail;
use Illuminate\Support\Facades\Mail;
use App\Services\LeaseApplicationNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaseApplicationAdminController extends Controller
{
    public function index(Request $request)
    {
        $applications = $this->visibleApplications($request)
            ->with(['listing', 'property'])
            ->latest('submitted_at')
            ->paginate(25);

        return view('admin.lease-applications.index', compact('applications'));
    }

    public function show(Request $request, LeaseApplication $application)
    {
        $this->authorizeApplication($request, $application);
        abort_unless($application->status !== 'draft', 404);
        $application->load(['listing', 'property', 'hostPaymentMethod', 'charges', 'offers.installments', 'offers.booking']);
        $paymentMethods = HostPaymentMethod::query()->where('owner_id', $application->property->owner_id)->where('enabled', true)->orderBy('id')->get();
        $leaseTemplate = app(\App\Services\LeaseAgreementService::class)->templateFor($application->property);
        $owner = $application->property->owner;
        $canCard = filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && app(\App\Services\Payments\PaymentService::class)->isConfigured();

        return view('admin.lease-applications.show', compact('application', 'paymentMethods', 'leaseTemplate', 'canCard'));
    }

    public function update(
        Request $request,
        LeaseApplication $application,
        LeaseApplicationNotificationService $notifications,
    ) {
        $this->authorizeApplication($request, $application);
        $data = $request->validate([
            'status' => ['required', 'in:in_review,approved,declined'],
            'host_notes' => ['nullable', 'string', 'max:10000'],
            'decline_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($data['status'] === 'approved') {
            $needsFront = ! $application->id_front_approved_at;
            $needsBack = $application->id_back_scan_path && ! $application->id_back_approved_at;
            if ($application->id_scan_path && ($needsFront || $needsBack)) {
                return back()->withErrors(['status' => 'Approve the applicant\'s ID before approving the application.']);
            }
        }
        $previousStatus = $application->status;
        $firstApproval = $data['status'] === 'approved' && ! $application->approved_at;
        $application->update([
            'status' => $data['status'],
            'host_notes' => $data['host_notes'] ?? null,
            'declined_at' => $data['status'] === 'declined'
                ? ($previousStatus === 'declined' ? $application->declined_at : now())
                : null,
            'decline_reason' => $data['status'] === 'declined'
                ? (trim((string) ($data['decline_reason'] ?? '')) ?: null)
                : null,
            'approved_at' => $firstApproval ? now() : $application->approved_at,
        ]);
        if ($previousStatus !== $application->status) {
            $notifications->statusChanged($application->fresh(), $firstApproval);
        }

        return back()->with('success', 'Application updated.');
    }


    public function createOffer(Request $request, LeaseApplication $application, LeaseOfferService $offers)
    {
        $this->authorizeApplication($request, $application);
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'monthly_rent' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'fees' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'initial_due' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'contract_html' => ['required', 'string', 'max:50000'],
            'payment_method' => ['required', 'in:card,manual'],
            'host_payment_method_id' => ['required_if:payment_method,manual', 'nullable', 'integer'],
            'accept_by' => ['required', 'date', 'after:now', 'before_or_equal:'.now()->addDays(30)->toDateTimeString()],
        ]);
        $offer = $offers->create($application, $data, (int) $request->user()->id);
        try {
            Mail::to($application->applicant_email)->send(new DirectBookingMail(
                'Your rental offer is ready',
                'The host has prepared a rental offer for '.$application->listing->public_title.'. Review the dates, payment schedule and agreement, then sign before the acceptance deadline.',
                route('public.lease-offer.show', [$application->id, $application->token, $offer->token]),
                'Review rental offer',
            ));
        } catch (\Throwable $e) { report($e); }
        return back()->with('success', 'Rental offer sent. The dates are now temporarily held until '.$offer->accept_by->format('M j, Y g:i A').'.');
    }


    public function cancelOffer(Request $request, LeaseApplication $application, LeaseOffer $offer, LeaseOfferService $service)
    {
        $this->authorizeApplication($request, $application);
        abort_unless((int) $offer->lease_application_id === (int) $application->id, 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $service->cancel($offer, (int) $request->user()->id, (string) ($data['reason'] ?? ''));
        return back()->with('success', 'Rental offer cancelled and its calendar dates released. Paid installments remain in the payment history; review any refund separately.');
    }

    public function markInstallmentReceived(Request $request, LeaseApplication $application, LeaseOffer $offer, LeaseInstallment $installment, LeaseOfferService $service)
    {
        $this->authorizeApplication($request, $application);
        abort_unless((int) $offer->lease_application_id === (int) $application->id && (int) $installment->lease_offer_id === (int) $offer->id, 404);
        $service->markInstallmentPaid($installment, (int) $request->user()->id);
        return back()->with('success', 'Installment payment recorded.');
    }

    public function markFeeReceived(
        Request $request,
        LeaseApplication $application,
        LeaseApplicationNotificationService $notifications,
    ) {
        $this->authorizeApplication($request, $application);
        abort_unless(
            in_array($application->status, ['submitted', 'in_review'], true)
                && $application->payment_method === 'manual'
                && $application->fee_status === 'pending',
            409,
            'This application has no pending manual fee.',
        );

        $application->update(['fee_status' => 'paid']);
        $notifications->feePaid($application->fresh());

        return back()->with('success', 'Manual application fee recorded.');
    }

    public function requestScreening(
        Request $request,
        LeaseApplication $application,
        LeaseApplicationNotificationService $notifications,
    ) {
        $this->authorizeApplication($request, $application);
        abort_unless(in_array($application->status, ['submitted', 'in_review', 'approved'], true), 409);
        abort_unless(filled($application->listing->screening_provider_url), 422, 'No screening provider is configured for this listing.');

        $application->update(['screening_requested_at' => now()]);
        $notifications->screeningRequested($application->fresh(['listing']));

        return back()->with('success', 'Screening instructions sent to the applicant.');
    }

    public function approveIdSide(Request $request, LeaseApplication $application, string $side)
    {
        $this->authorizeApplication($request, $application);
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        $application->forceFill([
            "id_{$side}_approved_at" => now(),
            "id_{$side}_declined_reason" => null,
        ])->save();

        return back()->with('success', ucfirst($side).' of ID approved.');
    }

    public function declineIdSide(Request $request, LeaseApplication $application, string $side)
    {
        $this->authorizeApplication($request, $application);
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        $data = $request->validate(['decline_reason' => ['required', 'string', 'max:1000']]);
        $application->forceFill([
            "id_{$side}_approved_at" => null,
            "id_{$side}_declined_reason" => $data['decline_reason'],
        ])->save();

        return back()->with('success', ucfirst($side).' of ID declined.');
    }

    public function idDocument(Request $request, LeaseApplication $application)
    {
        $this->authorizeApplication($request, $application);
        $file = $request->query('side') === 'back' ? $application->id_back_scan_path : $application->id_scan_path;
        abort_unless($file && Storage::disk('local')->exists($file), 404);

        $response = response()->file(Storage::disk('local')->path($file));
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function visibleApplications(Request $request)
    {
        $query = LeaseApplication::query()->where('status', '!=', 'draft');
        if (! $request->user()->hasRole('admin')) {
            $query->whereHas('property', fn ($property) => $property->where('owner_id', $request->user()->id));
        }

        return $query;
    }

    private function authorizeApplication(Request $request, LeaseApplication $application): void
    {
        abort_unless(
            $request->user()->hasRole('admin')
                || (int) $application->property()->value('owner_id') === (int) $request->user()->id,
            403,
        );
    }
}
