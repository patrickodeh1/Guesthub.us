<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book {{ $listing->public_title }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#fff;color:#17221e;font:15px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .wrap{max-width:760px;margin:0 auto;padding:18px}
        .card{border:1px solid #dfe6e1;border-radius:16px;overflow:hidden;background:#fff}
        .hero{display:grid;grid-template-columns:180px minmax(0,1fr);gap:16px;padding:16px;border-bottom:1px solid #e7ece8}
        .hero img{width:100%;height:130px;object-fit:cover;border-radius:10px}
        h1{font-size:20px;line-height:1.25;margin:4px 0 6px}
        .muted{color:#66746d;font-size:13px}
        form{padding:18px;display:grid;gap:16px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        label{display:grid;gap:6px;font-size:13px;font-weight:650;color:#34433c}
        input,select{width:100%;min-height:46px;border:1px solid #cfd8d2;border-radius:10px;padding:10px 12px;background:#fff;color:#17221e;font:inherit}
        input:focus,select:focus{outline:2px solid #1f6f54;outline-offset:1px;border-color:#1f6f54}
        .summary{border:1px solid #e1e7e2;border-radius:12px;padding:14px;background:#f9fbfa}
        .summary-head{display:flex;justify-content:space-between;gap:12px;font-weight:750}
        .lines{display:grid;gap:6px;margin-top:10px;font-size:13px;color:#53635a}
        .line{display:flex;justify-content:space-between;gap:10px}
        .status{font-size:12px;color:#66746d;margin-top:8px}
        .error{font-size:12px;color:#a12b2b;min-height:1em}
        .terms{display:flex;align-items:flex-start;gap:9px;font-size:12px;font-weight:400}
        .terms input{width:16px;min-height:16px;margin-top:3px}
        button{min-height:48px;border:0;border-radius:11px;padding:12px 18px;background:#1f6f54;color:#fff;font:inherit;font-weight:750;cursor:pointer}
        button:disabled{opacity:.55;cursor:not-allowed}
        .foot{text-align:center;font-size:11px;color:#7b8981;margin-top:12px}
        @media(max-width:560px){.wrap{padding:10px}.hero{grid-template-columns:104px minmax(0,1fr);gap:12px;padding:12px}.hero img{height:100px}h1{font-size:17px}.grid{grid-template-columns:1fr}form{padding:14px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="hero">
            @php
                $photo = $listing->photos->first();
                $photoUrl = $photo?->url ?? ($photo?->path ? asset('storage/'.$photo->path) : null);
            @endphp
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $listing->public_title }}">
            @endif
            <div>
                <p class="muted" style="margin:0;text-transform:uppercase;letter-spacing:.08em;font-weight:750">Direct booking</p>
                <h1>{{ $listing->public_title }}</h1>
                <p class="muted" style="margin:0">{{ $listing->property->city }}@if($listing->property->state), {{ $listing->property->state }}@endif · Up to {{ $listing->max_guests }} guests</p>
                <p class="muted" style="margin:7px 0 0">Secure booking powered by GuestHub</p>
            </div>
        </div>

        <div id="embed-booking-form" class="grid gap-4 p-4">
            <div class="grid grid-cols-2 gap-3">
                <label>Check-in
                    <input type="date" id="embed-check-in" min="{{ $minimumDate }}" required>
                </label>
                <label>Check-out
                    <input type="date" id="embed-check-out" min="{{ $minimumDate }}" required>
                </label>
                <label>Guests
                    <input type="number" id="embed-guests" min="1" max="{{ $listing->max_guests }}" value="1" required>
                </label>
                <label>Payment method
                    <select id="embed-payment-method" required>
                        @if($canCard)<option value="card">Credit or debit card</option>@endif
                        @if($manualMethods->isNotEmpty())<option value="manual">Pay host directly</option>@endif
                    </select>
                </label>
            </div>

            <div class="summary">
                <div class="summary-head"><span>Stay total</span><strong id="embed-total">Choose your dates</strong></div>
                <div id="embed-lines" class="lines"></div>
                <p id="embed-quote-status" class="status" role="status">Choose check-in and check-out to calculate your price.</p>
            </div>

            <p id="embed-error" class="error" role="alert"></p>
            <button type="button" id="embed-submit" disabled>Continue to booking</button>
            <p class="muted" style="margin:0;text-align:center">You'll enter your details and complete payment securely on GuestHub.</p>
        </div>
    </div>
    <p class="foot">Booking and payment are completed securely on GuestHub.</p>
</div>

<script>
(() => {
    const ci = document.getElementById('embed-check-in');
    const co = document.getElementById('embed-check-out');
    const guests = document.getElementById('embed-guests');
    const payment = document.getElementById('embed-payment-method');
    const total = document.getElementById('embed-total');
    const lines = document.getElementById('embed-lines');
    const status = document.getElementById('embed-quote-status');
    const error = document.getElementById('embed-error');
    const submit = document.getElementById('embed-submit');
    const currency = new Intl.NumberFormat(undefined, {style:'currency', currency:'USD'});
    let controller = null;
    let sequence = 0;
    let validQuote = false;

    async function quote() {
        const current = ++sequence;
        validQuote = false;
        submit.disabled = true;
        error.textContent = '';
        if (controller) controller.abort();
        lines.replaceChildren();

        if (!ci.value || !co.value || co.value <= ci.value) {
            total.textContent = 'Choose your dates';
            status.textContent = 'Choose check-in and check-out to calculate your price.';
            return;
        }

        status.textContent = 'Calculating your stay…';
        controller = new AbortController();
        const params = new URLSearchParams({
            check_in: ci.value,
            check_out: co.value,
            guests: guests.value || '1',
            payment_method: payment.value
        });

        try {
            const response = await fetch(@json($quoteUrl) + '?' + params.toString(), {
                headers: {'Accept':'application/json'},
                signal: controller.signal
            });
            const data = await response.json().catch(() => ({}));
            if (current !== sequence) return;
            if (!response.ok) {
                total.textContent = 'Unavailable';
                status.textContent = data.message || Object.values(data.errors || {}).flat()[0] || 'These dates are not available.';
                return;
            }

            data.line_items.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'line';
                const label = document.createElement('span');
                label.textContent = item.label;
                const amount = document.createElement('strong');
                amount.textContent = currency.format(item.amount_cents / 100);
                row.append(label, amount);
                lines.append(row);
            });
            total.textContent = currency.format(data.total_cents / 100);
            status.textContent = data.nights + (data.nights === 1 ? ' night' : ' nights') + '. Availability is checked again when you continue.';
            validQuote = true;
            submit.disabled = false;
        } catch (exception) {
            if (exception.name !== 'AbortError' && current === sequence) {
                total.textContent = 'Unable to calculate';
                status.textContent = 'Please try again.';
            }
        }
    }

    ci.addEventListener('change', () => {
        if (ci.value) {
            const next = new Date(ci.value + 'T00:00:00');
            next.setDate(next.getDate() + 1);
            co.min = next.toISOString().slice(0, 10);
            if (co.value && co.value <= ci.value) co.value = '';
        }
        quote();
    });
    co.addEventListener('change', quote);
    guests.addEventListener('change', quote);
    payment.addEventListener('change', quote);

    submit.addEventListener('click', () => {
        if (!validQuote || submit.disabled) {
            error.textContent = 'Choose available dates and wait for the price to finish calculating.';
            return;
        }
        const url = new URL(@json(route('public.checkout.show', $listing->listing_slug)));
        url.searchParams.set('check_in', ci.value);
        url.searchParams.set('check_out', co.value);
        url.searchParams.set('guests', guests.value || '1');
        url.searchParams.set('payment_method', payment.value);
        window.top.location.assign(url.toString());
    });
})();
</script>
</body>
</html>
