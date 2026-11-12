@extends('layouts.public')

@section('title', 'Consent and fee · Lease application')
@section('meta_description', 'Review screening consent and submit your application.')

@section('content')
    @php
        $listing = $application->listing;
        $fee = (int) ($listing->lease_application_fee_cents ?? 0);
        $photo = $listing->photos->first();
        $photoUrl = $photo?->url ?? ($photo?->path ? asset('storage/'.$photo->path) : null);
        $defaultMethod = old('payment_method', $canCard ? 'card' : 'manual');
        $refundText = match ($listing->lease_fee_refund_policy) {
            'non_refundable' => 'The application fee is non-refundable.',
            'refundable_if_declined' => 'The application fee is refunded if the host declines your application.',
            default => 'The host has not stated whether this fee is refundable. Please contact the host before paying.',
        };
    @endphp

    <style>
        .co{display:grid;gap:28px;max-width:1040px;margin:0 auto;padding-bottom:40px}
        @media(min-width:900px){.co{grid-template-columns:minmax(0,1.25fr) minmax(0,.85fr);align-items:start}}
        .co-main,.co-side{min-width:0}
        @media(min-width:900px){.co-side{position:sticky;top:90px}}
        .co h1{font-size:clamp(1.5rem,3vw,2rem);margin:0 0 6px}
        .co-sub{color:#66746d;margin:0 0 24px}
        .co-section{border-bottom:1px solid #e1e7e2;padding:0 0 26px;margin:0 0 26px}
        .co-section h2{font-size:1.2rem;margin:0 0 14px}
        .co-field{display:grid;gap:6px}
        .co-field label{font-size:.8rem;font-weight:700}
        .co-field input{border:1px solid #cfd8d2;border-radius:12px;padding:13px 14px;font:inherit;min-height:48px;width:100%;box-sizing:border-box;background:#fff}
        .co-field input:focus{outline:2px solid #1f6f54;outline-offset:1px;border-color:#1f6f54}
        .co-pay{display:flex;gap:12px;align-items:flex-start;border:1px solid #cfd8d2;border-radius:14px;padding:14px 16px;margin:0 0 10px;cursor:pointer}
        .co-pay:has(input:checked){border-color:#1f6f54;box-shadow:0 0 0 1px #1f6f54;background:#f4faf7}
        .co-pay input{margin-top:4px;width:auto;min-height:0}
        .co-pay strong{display:block}
        .co-pay span{display:block;color:#66746d;font-size:.86rem;margin-top:2px}
        .co-terms{display:flex;gap:10px;align-items:flex-start;font-size:.9rem;line-height:1.5;margin:0 0 12px}
        .co-terms input{width:auto;min-height:0;margin-top:4px}
        .co-btn{width:100%;border:0;border-radius:14px;background:#1f6f54;color:#fff;font:inherit;font-weight:700;font-size:1.02rem;padding:16px;cursor:pointer;margin-top:18px}
        .co-btn:hover{background:#185a44}
        .co-btn[disabled]{opacity:.6;cursor:wait}
        .co-note{color:#66746d;font-size:.82rem;text-align:center;margin:10px 0 0}
        .co-card{border:1px solid #dfe6e1;border-radius:18px;box-shadow:0 6px 24px #17221e1a;overflow:hidden;background:#fff}
        .co-card img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover}
        .co-card-body{padding:20px}
        .co-card h3{margin:0 0 4px;font-size:1.05rem;line-height:1.3}
        .co-card .place{color:#66746d;font-size:.88rem;margin:0 0 16px}
        .co-rows{display:grid;gap:12px;border-top:1px solid #e1e7e2;padding-top:16px;font-size:.92rem}
        .co-row{display:flex;justify-content:space-between;gap:12px}
        .co-row span:first-child{color:#66746d}
        .co-row strong{text-align:right}
        .co-policy{border-top:1px solid #e1e7e2;margin-top:16px;padding-top:16px;font-size:.85rem;color:#34433c;line-height:1.5}
        .co-policy b{display:block;margin-bottom:4px;color:#17221e}
        .co-error{border:1px solid #f0c4c4;background:#fdf1f1;color:#8a1f1f;border-radius:12px;padding:12px 14px;margin:0 0 20px;font-size:.92rem}
        .co-hp{position:absolute;left:-10000px}
    </style>

    <div class="co">
        <div class="co-main">
            <div class="eyebrow">Lease application · Step 4 of 4</div>
            <h1>Review and submit</h1>
            <p class="co-sub">We use your details only to review your application and verify your identity. They are kept private.</p>

            @if($errors->any())
                <div class="co-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form id="consent-form" method="post" action="{{ route('public.lease-application.submit', [$application->id, $application->token]) }}" novalidate>
                @csrf
                <div class="co-hp" aria-hidden="true"><input type="text" name="website" value="" tabindex="-1" autocomplete="off"></div>

                @if($fee > 0)
                    <section class="co-section">
                        <h2>Application fee</h2>
                        <p style="margin:0 0 14px"><strong>${{ number_format($fee / 100, 2) }}</strong>@if($canCard) <span class="muted">A card processing fee may apply.</span>@endif</p>
                        @if($canCard || $manualMethods->isNotEmpty())
                            @if($canCard)
                                <label class="co-pay">
                                    <input type="radio" name="payment_method" value="card" @checked($defaultMethod === 'card')>
                                    <div><strong>Credit or debit card</strong><span>Pay securely by card on the next step.</span></div>
                                </label>
                            @endif
                            @foreach($manualMethods as $method)
                                <label class="co-pay">
                                    <input type="radio" name="payment_method" value="manual" data-manual-method="{{ $method->id }}" @checked($defaultMethod === 'manual' && (old('host_payment_method_id') ? old('host_payment_method_id') == $method->id : $loop->first))>
                                    <div><strong>{{ $method->label }}</strong><span>Payment instructions are shown on the next step. The host confirms receipt.</span></div>
                                </label>
                            @endforeach
                            <input type="hidden" name="host_payment_method_id" id="host-payment-method-id" value="{{ old('host_payment_method_id') }}">
                        @else
                            <input type="hidden" name="payment_method" value="card">
                        @endif
                        <div class="co-policy" style="border-top:0;margin-top:8px;padding-top:0"><b>Refund policy</b>{{ $refundText }}</div>
                    </section>
                @else
                    <input type="hidden" name="payment_method" value="card">
                @endif

                <section class="co-section">
                    <h2>Consent and signature</h2>
                    <label class="co-terms">
                        <input type="checkbox" name="screening_consent" value="1" required @checked(old('screening_consent'))>
                        <span>I authorize the host to review my application, verify my identity and run tenant screening, and I agree to the application terms.</span>
                    </label>
                    <label class="co-terms">
                        <input type="checkbox" name="sms_consent" value="1" @checked(old('sms_consent'))>
                        <span>Text me updates about this application and my lease. Msg and data rates may apply. Message frequency varies. Reply STOP to cancel, HELP for help.</span>
                    </label>
                    @if($fee > 0)
                        <label class="co-terms">
                            <input type="checkbox" name="fee_policy_ack" value="1" required @checked(old('fee_policy_ack'))>
                            <span>I understand the application fee policy shown above.</span>
                        </label>
                    @endif
                    <div class="co-field" style="margin-top:14px">
                        <label for="signature">Type your name as your signature</label>
                        <p class="co-note" style="margin:0;text-align:left">Middle names, accents, punctuation, and suffixes may differ from your application.</p>
                        <input id="signature" name="signature" value="{{ old('signature') }}" required maxlength="255" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="Full legal name">
                    </div>
                    <button class="co-btn" type="submit" id="co-submit">Submit application</button>
                    <p class="co-note">{{ $fee > 0 ? 'You will not be charged until the next step.' : 'There is no application fee.' }}</p>
                </section>
            </form>
        </div>

        <aside class="co-side" aria-label="Application summary">
            <div class="co-card">
                @if($photoUrl)<img src="{{ $photoUrl }}" alt="{{ $listing->public_title }}">@endif
                <div class="co-card-body">
                    <h3>{{ $listing->public_title }}</h3>
                    <p class="place">{{ $listing->property->city }}@if($listing->property->state), {{ $listing->property->state }}@endif</p>
                    <div class="co-rows">
                        <div class="co-row"><span>Applicant</span><strong>{{ $application->applicant_name }}</strong></div>
                        @if($application->move_in_date)<div class="co-row"><span>Move-in</span><strong>{{ $application->move_in_date->format('M j, Y') }}</strong></div>@endif
                        @if($application->lease_term_months)<div class="co-row"><span>Lease term</span><strong>{{ $application->lease_term_months }} months</strong></div>@endif
                        <div class="co-row"><span>Application fee</span><strong>{{ $fee > 0 ? '$'.number_format($fee / 100, 2) : 'None' }}</strong></div>
                    </div>
                    @if($fee > 0)<div class="co-policy"><b>Refund policy</b>{{ $refundText }}</div>@endif
                </div>
            </div>
        </aside>
    </div>

    <script>
        (() => {
            const form = document.getElementById('consent-form');
            const hostId = document.getElementById('host-payment-method-id');
            function setHostMethod() {
                if (!hostId) return;
                const sel = document.querySelector('input[name="payment_method"]:checked');
                hostId.value = (sel && sel.dataset.manualMethod) || '';
            }
            document.querySelectorAll('input[name="payment_method"]').forEach((i) => i.addEventListener('change', setHostMethod));
            setHostMethod();
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
                const b = document.getElementById('co-submit'); b.disabled = true; b.textContent = 'One moment...';
            });
        })();
    </script>
@endsection
