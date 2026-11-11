@if($booking->source === 'direct' && $booking->isCancelled() && $booking->payment_confirmed_at)
    @php
        $money = fn ($c) => '$'.number_format(((int) $c) / 100, 2);
        $isManual = $booking->payment_method === 'manual';
        $rCharge = $isManual ? null : $booking->charges()->where('type', \App\Models\Charge::TYPE_ACCOMMODATION)
            ->whereIn('status', [\App\Models\Charge::STATUS_SUCCESS, \App\Models\Charge::STATUS_PARTIALLY_CAPTURED, \App\Models\Charge::STATUS_REFUNDED])->latest()->first();
        $policy = app(\App\Services\DirectBookingCancellationPolicy::class);
        $remaining = $rCharge ? max(0, $rCharge->amount_cents - (int) $rCharge->refunded_cents) : (int) $booking->total_cents;
        $suggested = $rCharge ? $policy->refundableCents($booking, $rCharge, null) : 0;
        $maxKeepFee = $rCharge ? $policy->refundableCents($booking, $rCharge, 100) : 0;
    @endphp
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="section-title">Refund</h2>
        @if($booking->refund_status === 'awaiting_decision')
            <p class="mt-2 rounded-lg bg-amber-50 p-2 text-sm font-semibold text-amber-800">Waiting on refund decision</p>
            <form method="post" action="{{ route('admin.guests.direct-refund-close', $booking) }}" class="mt-2" onsubmit="return confirm('Close the refund and archive this booking? You can still unarchive it later.')">@csrf<button class="btn-secondary">Close refund (no further refund)</button></form>
        @elseif($booking->refund_status === 'resolved')
            <p class="mt-2 rounded-lg bg-emerald-50 p-2 text-sm text-emerald-800">Refund closed.</p>
        @endif
        @if($isManual)
            <p class="mt-2 text-sm text-slate-600">Paid outside the platform ({{ $money($booking->total_cents) }}). Send the refund yourself, then record it here.</p>
        @elseif(! $rCharge)
            <p class="mt-2 text-sm text-slate-600">No card payment found for this booking.</p>
        @else
            <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                <div><span class="block text-xs uppercase text-slate-400">Paid</span><strong>{{ $money($rCharge->amount_cents) }}</strong></div>
                <div><span class="block text-xs uppercase text-slate-400">Refunded</span><strong>{{ $money($rCharge->refunded_cents) }}</strong></div>
                <div><span class="block text-xs uppercase text-slate-400">Remaining</span><strong>{{ $money($remaining) }}</strong></div>
            </div>
        @endif
        @if($remaining > 0 && ($isManual || $rCharge))
            <form method="post" action="{{ route('admin.guests.direct-refund', $booking) }}" class="mt-4 grid gap-3"
                  onsubmit="return confirm('{{ $isManual ? 'Record' : 'Send' }} a refund of $' + this.amount.value + '?')">
                @csrf
                @if($isManual)<input type="hidden" name="manual" value="1">@endif
                <label class="text-sm font-medium">Amount (USD)
                    <input name="amount" id="refund-amount" class="input mt-1 w-full" inputmode="decimal" required placeholder="0.00">
                </label>
                @unless($isManual)
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button type="button" class="btn-secondary" onclick="document.getElementById('refund-amount').value='{{ number_format($suggested / 100, 2, '.', '') }}'">Policy suggests {{ $money($suggested) }}</button>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('refund-amount').value='{{ number_format($maxKeepFee / 100, 2, '.', '') }}'">Max {{ $money($maxKeepFee) }}</button>
                        <button type="button" class="btn-secondary" onclick="document.getElementById('refund-amount').value='{{ number_format($remaining / 100, 2, '.', '') }}'">Everything left {{ $money($remaining) }}</button>
                    </div>
                @endunless
                <label class="text-sm font-medium">Note (optional)
                    <input name="note" class="input mt-1 w-full" maxlength="500">
                </label>
                <button class="btn-primary">{{ $isManual ? 'Record refund' : 'Send refund' }}</button>
            </form>
            <p class="mt-2 text-xs text-slate-500">The policy figure is calculated as of today, not the day it was cancelled. Stripe normally keeps its own card fee.</p>
        @elseif($remaining <= 0)
            <p class="mt-3 rounded-lg bg-emerald-50 p-2 text-sm text-emerald-800">Fully refunded.</p>
        @endif
    </div>
@endif
