@if($booking->source === 'direct' && $booking->payment_method === 'manual' && $booking->status === \App\Models\Booking::STATUS_PENDING_PAYMENT && ! $booking->payment_confirmed_at && ! $booking->guest_payment_reported_at)
    <div class="mt-4 rounded-xl border border-slate-200 p-4">
        <p class="text-sm font-semibold text-slate-900">Already sent your payment?</p>
        <p class="mt-1 text-sm text-slate-600">Upload a screenshot, photo or PDF of your receipt. Your stay is confirmed once the host confirms they received the money.</p>
        <form method="post" enctype="multipart/form-data" action="{{ route('public.checkout.payment-report', [$booking->booking_id, $booking->token]) }}" class="mt-3">
            @csrf
            <input type="file" name="receipt" accept="image/*,application/pdf" required class="mt-1 block w-full cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
            @error('receipt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">I sent my payment</button>
        </form>
    </div>
@endif
