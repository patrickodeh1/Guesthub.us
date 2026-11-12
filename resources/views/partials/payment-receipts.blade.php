@php
    $viewer = $viewer ?? 'admin';
    $receiptList = $receipts ?? app(\App\Services\PaymentReceiptService::class)->forPayable($payable);
    $reportedAt = $payable->guest_payment_reported_at ?? null;
    $reportedAt = $reportedAt ? \Illuminate\Support\Carbon::parse($reportedAt) : null;
@endphp
@if($reportedAt || $receiptList->isNotEmpty())
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        @if($reportedAt && $viewer === 'admin' && ! $payable->payment_confirmed_at)
            <p class="font-semibold text-amber-800">Guest reported a payment {{ $reportedAt->format('M j, g:i A') }}. Check your account, then upload your receipt and confirm.</p>
        @endif
        @foreach($receiptList as $r)
            @php
                $url = $viewer === 'guest'
                    ? route('public.checkout.receipt', [$payable->booking_id, $payable->token, $r])
                    : route('admin.guests.payment-receipt', [$payable, $r]);
            @endphp
            <p class="mt-1 text-slate-700">
                {{ $r->role === 'host' ? 'Host received receipt' : 'Guest payment receipt' }}
                <span class="text-slate-400">· {{ $r->created_at->format('M j, g:i A') }}</span>
                · <button type="button" class="font-semibold text-slate-900 underline" data-receipt-url="{{ $url }}" onclick="const dialog = document.getElementById('payment-receipt-{{ $r->id }}'); dialog.querySelector('iframe').src = this.dataset.receiptUrl; dialog.showModal();">View</button>
            </p>
            <dialog id="payment-receipt-{{ $r->id }}" aria-label="Payment receipt" class="m-auto max-w-[95vw] rounded-xl p-0 shadow-2xl backdrop:bg-slate-950/60" style="width:min(95vw,64rem);height:90vh;max-height:90vh">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <p class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ $r->original_name ?: 'Payment receipt' }}</p>
                    <form method="dialog"><button class="btn-secondary shrink-0" aria-label="Close receipt">Close</button></form>
                </div>
                <iframe title="Payment receipt preview" class="block w-full border-0" style="height:calc(90vh - 4rem)"></iframe>
            </dialog>
        @endforeach
    </div>
@endif
