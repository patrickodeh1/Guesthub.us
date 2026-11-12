<?php
namespace App\Services;

use App\Mail\DirectBookingMail;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\HostPaymentMethod;
use App\Models\LeaseApplication;
use App\Models\LeaseInstallment;
use App\Models\LeaseOffer;
use App\Services\Payments\PaymentService;
use App\Services\Pms\AvailabilityLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaseOfferService
{
    public function create(LeaseApplication $application, array $data, int $hostId): LeaseOffer
    {
        abort_unless((int) $application->property?->owner_id === $hostId || auth()->user()?->hasRole('admin'), 403);
        abort_unless($application->status === 'approved' && $application->fee_status === 'paid', 409);
        abort_if($application->offers()->whereIn('status', ['sent','signed','confirmed'])->exists(), 409, 'An active or confirmed offer already exists.');
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $months = (int) $data['term_months'];
        $rent = (int) round((float) $data['monthly_rent'] * 100);
        $deposit = (int) round((float) ($data['deposit'] ?? 0) * 100);
        $fees = (int) round((float) ($data['fees'] ?? 0) * 100);
        $initialRentCents = $rent;
        $initial = $rent + $deposit + $fees;
        $total = $rent * $months + $deposit + $fees;
        if ($rent < 1) {
            throw ValidationException::withMessages(['monthly_rent' => 'Monthly rent must be greater than zero.']);
        }
        if ($end->lte($start) || $start->lt(now()->startOfDay())) throw ValidationException::withMessages(['start_date' => 'Choose a valid future rental period.']);

        $method = null;
        if ($data['payment_method'] === 'manual') {
            $method = HostPaymentMethod::query()->whereKey($data['host_payment_method_id'] ?? 0)->where('owner_id', $application->property->owner_id)->where('enabled', true)->first();
            if (! $method) throw ValidationException::withMessages(['host_payment_method_id' => 'Choose an enabled host payment method.']);
        } else {
            $owner = $application->property->owner;
            abort_unless(filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && app(PaymentService::class)->isConfigured(), 422, 'Card payments are not available for this host.');
        }
        $deadline = Carbon::parse($data['accept_by']);
        abort_if($deadline->lte(now()) || $deadline->gt(now()->addDays(30)), 422, 'The acceptance deadline must be within 30 days.');

        return BookingConflictService::withPropertyLock((int) $application->property_id, function () use ($application,$data,$start,$end,$months,$rent,$deposit,$fees,$initial,$initialRentCents,$total,$method,$deadline): LeaseOffer {
            BookingConflictService::assertFree((int) $application->property_id, $start, $end);
            $application->refresh();
            abort_unless($application->status === 'approved' && $application->fee_status === 'paid', 409);
            $ref = 'LS-'.strtoupper(Str::random(8));
            $booking = Booking::create([
                'booking_id'=>$ref,'reservation_id'=>$ref,'source'=>'lease',
                'guest_name'=>$application->applicant_name,'phone'=>$application->applicant_phone,'email'=>$application->applicant_email,
                'property_id'=>$application->property_id,'token'=>Str::random(48),'check_in_date'=>$start->toDateString(),
                'check_out_date'=>$end->toDateString(),'status'=>Booking::STATUS_PENDING_PAYMENT,
                'guests_count'=>max(1,(int)$application->occupants),'payment_method'=>$data['payment_method'],
                'host_payment_method_id'=>$method?->id,'accommodation_cents'=>$rent*$months,'cleaning_fee_cents'=>$fees,
                'tax_cents'=>0,'processing_fee_cents'=>0,'total_cents'=>$total,'hold_expires_at'=>$deadline,
                'lease_application_id'=>$application->id,
            ]);
            $offer = LeaseOffer::create([
                'lease_application_id'=>$application->id,'property_id'=>$application->property_id,'booking_id'=>$booking->id,
                'status'=>LeaseOffer::STATUS_SENT,'token'=>Str::random(64),'start_date'=>$start->toDateString(),'end_date'=>$end->toDateString(),
                'monthly_rent_cents'=>$rent,'deposit_cents'=>$deposit,'fees'=>[['label'=>'Additional fees','amount_cents'=>$fees]],
                'total_cents'=>$total,'initial_due_cents'=>$initial,'payment_method'=>$data['payment_method'],
                'host_payment_method_id'=>$method?->id,'accept_by'=>$deadline,'contract_html'=>$data['contract_html'],
                'contract_version'=>'lease-offer-v1','term_months'=>$months,
                'host_signed_name'=>auth()->user()?->name ?: $application->property->owner?->name,
                'host_signed_at'=>now(),'host_signed_ip'=>request()->ip(),
                'host_signed_user_agent'=>mb_substr((string)request()->userAgent(),0,1000),'sent_at'=>now(),
            ]);
            $booking->update(['lease_offer_id'=>$offer->id]);
            foreach ([['deposit',$deposit,1],['fee',$fees,2]] as [$kind,$amount,$sort]) {
                if ($amount > 0) $offer->installments()->create([
                    'kind'=>$kind,'label'=>$kind==='deposit'?'Security deposit':'Additional fees',
                    'due_date'=>$start->toDateString(),'amount_cents'=>$amount,'required_at_signing'=>true,'sort_order'=>$sort,
                ]);
            }
            $remainingInitialRent = $initialRentCents;
            for ($m=0;$m<$months;$m++) {
                $due=$start->copy()->addMonthsNoOverflow($m);
                $requiredNow = $remainingInitialRent >= $rent;
                $offer->installments()->create([
                    'kind'=>LeaseInstallment::KIND_RENT,'label'=>'Rent · '.$due->format('F Y'),
                    'due_date'=>$due->toDateString(),'amount_cents'=>$rent,'required_at_signing'=>$requiredNow,'sort_order'=>10+$m,
                ]);
                if ($requiredNow) $remainingInitialRent -= $rent;
            }
            app(AvailabilityLedger::class)->syncBooking($booking);
            return $offer->load(['installments','booking','application']);
        });
    }

    public function signGuest(LeaseOffer $offer,string $name,string $ip,string $agent): void
    {
        DB::transaction(function() use($offer,$name,$ip,$agent): void {
            $locked=LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status===LeaseOffer::STATUS_SENT && $locked->accept_by?->isFuture(),409,'This offer is no longer available.');
            abort_unless(mb_strtolower(trim($name))===mb_strtolower(trim((string)$locked->application->applicant_name)),422,'Enter your full name exactly as it appears on your application.');
            $locked->update(['guest_signed_name'=>trim($name),'guest_signed_at'=>now(),'guest_signed_ip'=>$ip,'guest_signed_user_agent'=>mb_substr($agent,0,1000),'status'=>LeaseOffer::STATUS_SIGNED]);
        });
        $this->confirmIfReady($offer->fresh());
    }

    public function markInitialPaymentPaid(LeaseOffer $offer, int $hostId): void
    {
        $offer->loadMissing(['property', 'installments', 'booking']);
        abort_unless((int) $offer->property->owner_id === $hostId || auth()->user()?->hasRole('admin'), 403);
        abort_unless($offer->payment_method === 'manual', 409, 'This offer uses online card payments.');
        abort_unless(in_array($offer->status, [LeaseOffer::STATUS_SIGNED, LeaseOffer::STATUS_CONFIRMED], true), 409);
        if ($offer->status === LeaseOffer::STATUS_SIGNED) {
            abort_unless($offer->accept_by?->isFuture(), 409, 'This offer has expired.');
        }

        DB::transaction(function () use ($offer, $hostId): void {
            $locked = LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $required = $locked->installments()->where('required_at_signing', true)->lockForUpdate()->get();
            abort_unless($required->isNotEmpty() && $required->every(fn ($item) => $item->status === LeaseInstallment::STATUS_PENDING), 409, 'The initial payment has already been recorded or needs review.');
            $paidAt = now();
            foreach ($required as $item) {
                $item->update([
                    'status' => LeaseInstallment::STATUS_PAID,
                    'method' => 'manual',
                    'paid_at' => $paidAt,
                    'received_by' => $hostId,
                ]);
            }
        });

        $this->confirmIfReady($offer->fresh());
    }

    public function markInstallmentPaid(LeaseInstallment $installment,int $hostId): void
    {
        $offer=$installment->offer()->with('booking')->firstOrFail();
        abort_unless((int)$offer->property->owner_id===$hostId || auth()->user()?->hasRole('admin'),403);
        abort_unless(in_array($offer->status,[LeaseOffer::STATUS_SIGNED,LeaseOffer::STATUS_CONFIRMED],true) && $offer->payment_method==='manual',409);
        if ($offer->status === LeaseOffer::STATUS_SIGNED) abort_unless($offer->accept_by?->isFuture(), 409, 'This offer has expired.');
        abort_unless(! $installment->required_at_signing, 409, 'The initial rent, deposit and fees must be recorded together.');
        abort_unless($installment->status===LeaseInstallment::STATUS_PENDING,409);
        $installment->update(['status'=>LeaseInstallment::STATUS_PAID,'method'=>'manual','paid_at'=>now(),'received_by'=>$hostId]);
        $this->confirmIfReady($offer);
    }

    public function cancel(LeaseOffer $offer,int $hostId,string $reason=''): void
    {
        abort_unless((int)$offer->property->owner_id===$hostId || auth()->user()?->hasRole('admin'),403);
        abort_unless(in_array($offer->status,[LeaseOffer::STATUS_SENT,LeaseOffer::STATUS_SIGNED,LeaseOffer::STATUS_CONFIRMED],true),409);
        foreach (Charge::query()->where('lease_offer_id',$offer->id)->where('status',Charge::STATUS_PENDING)->get() as $charge) {
            try { app(PaymentService::class)->cancelPendingIntent($charge); $charge->update(['status'=>Charge::STATUS_FAILED]); }
            catch (Throwable $e) { report($e); }
        }
        BookingConflictService::withPropertyLock((int)$offer->property_id,function() use($offer,$reason): void {
            $locked=LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status,[LeaseOffer::STATUS_SENT,LeaseOffer::STATUS_SIGNED,LeaseOffer::STATUS_CONFIRMED],true),409);
            $locked->update(['status'=>LeaseOffer::STATUS_WITHDRAWN,'withdrawn_at'=>now(),'declined_reason'=>trim($reason)?:'Withdrawn by host']);
            $locked->installments()->where('status',LeaseInstallment::STATUS_PENDING)->update(['status'=>LeaseInstallment::STATUS_WAIVED]);
            $booking=Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->first();
            if($booking) $booking->update(['status'=>'cancelled','cancelled_at'=>now(),'hold_expires_at'=>null]);
        });
        if($offer->booking) app(AvailabilityLedger::class)->syncBooking($offer->booking->fresh());
    }

    public function renderContract(LeaseOffer $offer): string
    {
        $offer->loadMissing(['installments','application','property.owner']);
        $schedule='<table><tbody>';
        foreach($offer->installments as $item) $schedule.='<tr><td>'.e($item->label).' · '.e($item->due_date->format('M j, Y')).'</td><td>$'.number_format($item->amount_cents/100,2).'</td></tr>';
        $schedule.='</tbody></table>';
        $values=[
            '[[applicant_name]]'=>e($offer->application->applicant_name),
            '[[host_name]]'=>e($offer->property->owner?->host_name ?: $offer->property->owner?->name),
            '[[property_name]]'=>e($offer->property->name),
            '[[property_address]]'=>e((string)($offer->property->address ?? '')),
            '[[website_name]]'=>e(\App\Support\Branding::siteName()),
            '[[start_date]]'=>e($offer->start_date->format('M j, Y')),
            '[[end_date]]'=>e($offer->end_date->format('M j, Y')),
            '[[nights]]'=>(string)$offer->nights(),
            '[[monthly_rent]]'=>'$'.number_format($offer->monthly_rent_cents/100,2),
            '[[deposit]]'=>'$'.number_format($offer->deposit_cents/100,2),
            '[[total]]'=>'$'.number_format($offer->total_cents/100,2),
            '[[initial_due]]'=>'$'.number_format($offer->initial_due_cents/100,2),
            '[[payment_schedule]]'=>$schedule,
            '[[host_signed_name]]'=>e((string)$offer->host_signed_name),
            '[[host_signed_at]]'=>e($offer->host_signed_at?->format('M j, Y')),
            '[[tenant_signed_name]]'=>e((string)($offer->guest_signed_name ?: 'Awaiting tenant signature')),
            '[[tenant_signed_at]]'=>e($offer->guest_signed_at?->format('M j, Y') ?: 'Pending'),
            '[[tenant_signed_ip]]'=>e((string)($offer->guest_signed_ip ?: 'Pending')),
            '[[agreement_version]]'=>e((string)$offer->contract_version),
        ];
        return strtr((string)$offer->contract_html,$values);
    }

    public function confirmIfReady(LeaseOffer $offer): bool
    {
        $offer->loadMissing(['installments','booking']);
        if(!$offer->isFullySigned() || $offer->status===LeaseOffer::STATUS_CONFIRMED) return false;
        $required=$offer->installments->where('required_at_signing',true);
        if($required->isEmpty() || $required->contains(fn($i)=>!$i->isSettled())) return false;
        $confirmed=BookingConflictService::withPropertyLock((int)$offer->property_id,function() use($offer): bool {
            $locked=LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if($locked->status!==LeaseOffer::STATUS_SIGNED || !$locked->isFullySigned() || $locked->accept_by?->isPast()) return false;
            BookingConflictService::assertFree((int)$locked->property_id,$locked->start_date,$locked->end_date,(int)$locked->booking_id);
            $booking=Booking::query()->whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();
            $booking->update([
                'status'=>'pending','payment_confirmed_at'=>now(),'hold_expires_at'=>null,
                'contract_snapshot'=>$this->renderContract($locked),'contract_version'=>$locked->contract_version,
                'contract_accepted_at'=>$locked->guest_signed_at,'contract_signed_name'=>$locked->guest_signed_name,
                'contract_signed_ip'=>$locked->guest_signed_ip,'contract_signed_user_agent'=>$locked->guest_signed_user_agent,
            ]);
            $locked->update(['status'=>LeaseOffer::STATUS_CONFIRMED,'confirmed_at'=>now()]);
            return true;
        });
        if($confirmed) {
            $booking=$offer->booking->fresh(['property']);
            app(AvailabilityLedger::class)->syncBooking($booking);
            try {
                Mail::to($booking->email)->send(new DirectBookingMail(
                    'Your rental is confirmed',
                    'Your lease agreement has been signed and the required initial payment has been received. Your guest portal is ready.',
                    route('guest.show',[$booking->booking_id,$booking->token]),
                    'Open guest portal'
                ));
            } catch(Throwable $e) { report($e); }
        }
        return $confirmed;
    }
}
