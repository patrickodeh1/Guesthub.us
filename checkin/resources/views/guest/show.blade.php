<x-guest-layout :booking="$booking" :property="$property" :title="$property->name" :state="$state">
@php
    $categories = isset($categories) ? $categories : collect();
    $checkinSteps = isset($checkinSteps) ? $checkinSteps : [];
    $checkoutSteps = isset($checkoutSteps) ? $checkoutSteps : [];
    $parkingSteps = isset($parkingSteps) ? $parkingSteps : [];
    $heroImg = $property->heroImageUrl();
    $siteLogo = \App\Models\Setting::getValue('site_logo');
    $categoryColor = ['#eef2ff', '#3b65ce'];
    $guideCats = $categories;
    $idwNeedsContractSignature = filled(\App\Models\Setting::getValue('legal_rental_contract_content', ''))
        && ! $booking->contract_accepted_at;
    $idwDetailsComplete = filled($booking->email)
        && filled($booking->phone)
        && ! is_null($booking->parking_needed)
        && filled($booking->checkin_time_preference)
        && filled($booking->checkout_time_preference)
        && filled($booking->terms_accepted_at)
        && ! $idwNeedsContractSignature;
    // Which pre-check-in step to render server-side so a reload doesn't flash
    // the first form step before JS restores the real step.
    $idwStartStep = $idwDetailsComplete ? 2 : 1;
    // If the ID isn't fully approved yet, never let the wizard sit on the final
    // "get the August app" step (3) — the guest must be shown the ID step so a
    // required re-upload is obvious.
    $idwNeedsId = ! $booking->isIdFullyApproved();
@endphp

{{-- Conditional guest notices (Admin > Guest Notices). Rendered once per
     page load for the current phase; "once per booking" notices remember
     dismissal in localStorage. --}}
@if(($guestNoticePopups ?? collect())->isNotEmpty())
<div id="guest-notice-overlay" class="hidden fixed inset-0 z-[60] flex items-end justify-center bg-black/50 p-4 sm:items-center">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <div id="guest-notice-content"></div>
        <button type="button" id="guest-notice-dismiss" class="guest-primary-btn mt-6 w-full">Got it</button>
    </div>
</div>
<div id="guest-notice-source" class="hidden">
    @foreach($guestNoticePopups as $notice)
        <div class="guest-notice" data-notice-id="{{ $notice->id }}" data-once="{{ $notice->once_per_booking ? '1' : '0' }}" data-storage-key="guest_notice_{{ $booking->booking_id }}_{{ $notice->id }}">
            <h2 class="text-lg font-extrabold text-slate-950">{{ $notice->title }}</h2>
            <div class="mt-3 text-sm leading-6 text-slate-600">{!! nl2br(e($notice->body)) !!}</div>
        </div>
    @endforeach
</div>
<script>
(function () {
    var source = document.getElementById("guest-notice-source");
    var overlay = document.getElementById("guest-notice-overlay");
    if (!source || !overlay) return;

    var content = document.getElementById("guest-notice-content");
    var dismissBtn = document.getElementById("guest-notice-dismiss");

    var pending = Array.prototype.slice.call(source.querySelectorAll(".guest-notice")).filter(function (node) {
        if (node.dataset.once !== "1") return true;
        try { return !localStorage.getItem(node.dataset.storageKey); } catch (e) { return true; }
    });

    if (!pending.length) return;

    var index = 0;
    function show() {
        var node = pending[index];
        if (!node) { overlay.classList.add("hidden"); return; }
        content.innerHTML = "";
        var clone = node.cloneNode(true);
        content.appendChild(clone);
        overlay.classList.remove("hidden");
    }

    dismissBtn.addEventListener("click", function () {
        var node = pending[index];
        if (node && node.dataset.once === "1") {
            try { localStorage.setItem(node.dataset.storageKey, "1"); } catch (e) {}
        }
        index += 1;
        show();
    });

    show();
})();
</script>
@endif

@if($state === 'identity')
@endif

@if(! empty($previewMode))
    <div class="mb-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800">
        Admin preview - state: {{ $state }}
    </div>
@endif

<section class="phone-frame">
    <div class="phone-screen">
        <div class="ios-status">
            <span>9:41</span>
            <span class="ios-notch"></span>
            <span class="ios-signal">cell wifi battery</span>
        </div>

        @if($state === 'access_blocked')
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill" style="background:#fef2f2;color:#991b1b;">
                        <x-icon name="alert-triangle" class="h-4 w-4" />
                        Access blocked
                    </span>
                </div>
                <div class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center md:py-24">
                    <h1 class="guest-status-title">Access unavailable</h1>
                    <p class="max-w-md text-sm leading-6 text-slate-600">{!! nl2br(e(strip_tags($booking->access_blocked_reason))) !!}</p>
                </div>
            </div>
        @elseif($state === 'cancelled')
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill" style="background:#fef2f2;color:#991b1b;">
                        <x-icon name="x-circle" class="h-4 w-4" />
                        Cancelled
                    </span>
                </div>
                <div class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center md:py-24">
                    <div class="guest-big-check" style="background:#fef2f2;color:#991b1b;">
                        <x-icon name="x-circle" class="h-8 w-8" />
                    </div>
                    <h1 class="guest-status-title">This reservation was cancelled</h1>
                    <p class="max-w-md text-sm leading-6 text-slate-600">This stay has been cancelled, so check-in is no longer available. If you believe this is a mistake or have questions, please contact your host.</p>
                </div>
            </div>
        @elseif($state === 'unit_not_ready')
            <div data-poll-id-status="{{ route('guest.id-status', [$booking->booking_id, $booking->token]) }}" data-poll-fields="checkin_approved"></div>
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill">
                        <x-icon name="clock" class="h-4 w-4" />
                        Not checked in
                    </span>
                </div>
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full rounded-xl mt-4">
                <div class="p-6 md:p-10 text-center">
                    <div class="guest-big-check">
                        <x-icon name="clock" class="h-8 w-8" />
                    </div>
                    <h1 class="guest-status-title">Your unit isn't quite ready yet</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        We're putting the finishing touches on your unit. There's nothing you need to do — as soon as it's ready you'll be approved to check in and your arrival details will appear here.
                    </p>
                    <div class="guest-stay-grid mt-6">
                        <div class="guest-stay-tile">
                            <div class="guest-stay-tile-icon"><x-icon name="calendar" class="h-5 w-5" /></div>
                            <p class="guest-stay-tile-label">Check-In</p>
                            <p class="guest-stay-tile-date">{{ $booking->check_in_date->format('M d, Y') }}</p>
                            <p class="guest-stay-tile-time">{{ $booking->effectiveCheckinTimeFormatted() }}</p>
                        </div>
                        <div class="guest-stay-tile">
                            <div class="guest-stay-tile-icon"><x-icon name="calendar" class="h-5 w-5" /></div>
                            <p class="guest-stay-tile-label">Check-Out</p>
                            <p class="guest-stay-tile-date">{{ $booking->check_out_date->format('M d, Y') }}</p>
                            <p class="guest-stay-tile-time">{{ $booking->effectiveCheckoutTimeFormatted() }}</p>
                        </div>
                    </div>
                    <p class="mt-5 text-xs leading-5 text-slate-500">Please don't head to the property until this screen unlocks — that's when the address and entry steps appear.</p>
                </div>
            </div>
        @elseif($state === 'identity' && $booking->isIdentityComplete() && $booking->photo_id_received && ($booking->needsIdApproval() || ! $booking->isBackgroundCheckComplete()))
            <div data-poll-id-status="{{ route('guest.id-status', [$booking->booking_id, $booking->token]) }}" data-poll-fields="id_approved,background_check_complete"></div>
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                </div>
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full rounded-xl mt-4">
                <div class="p-6 md:p-10 text-center">
                    <div class="flex items-center justify-center mb-4">
                        <x-icon name="alert-triangle" class="h-6 w-6" style="color:#92400e;" />
                    </div>
                    <h1 class="guest-status-title">{{ $backgroundCheckStepName }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{!! nl2br(e(strip_tags($backgroundCheckStepInstructions))) !!}</p>
                    <p class="mt-4 text-xs text-slate-500 italic">This is all for now — your details are processing.</p>
                </div>
            </div>
        @elseif($state === 'identity')
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill">
                        <x-icon name="alert-triangle" class="h-4 w-4" />
                        Not checked in
                    </span>
                </div>
                {{-- Step indicator: big circled current step, dash-separated others. --}}
                <div class="px-6 pt-5 step-indicator" id="step-indicator-wrapper">
                    <span class="step-num" data-num="1" id="step-num-1">1</span>
                    <span class="step-dash">-</span>
                    <span class="step-num" data-num="2" id="step-num-2">2</span>
                    <span class="step-dash">-</span>
                    <span class="step-num" data-num="3" id="step-num-3">3</span>
                </div>
            </div>

            <div class="guest-portal-card mt-4">
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full rounded-xl">
            </div>

            <div class="guest-portal-card mt-4">
                <div data-poll-id-status="{{ route('guest.id-status', [$booking->booking_id, $booking->token]) }}" data-poll-fields="id_rejected" data-id-state-key="idw_form_state_{{ $booking->booking_id }}" data-id-rejection-key="idw_id_rejection_seen_{{ $booking->booking_id }}"></div>
                <form id="guest-booking-form" method="post" data-skip-loading enctype="multipart/form-data" action="{{ route('guest.identity', [$booking->booking_id, $booking->token]) }}" class="guest-booking-card">
                    @csrf

                    {{-- ══════════════════ STEP 1 — Stay details, contact, and consent ══════════════════ --}}
                    <div class="idw-step{{ $idwStartStep === 1 ? '' : ' hidden' }}" data-step="1">
                        <div class="mb-6">
                            <h2 class="text-xl font-extrabold text-slate-950">Your stay details</h2>
                            <div class="guest-stay-grid mt-4">
                                <div class="guest-stay-tile">
                                    <div class="guest-stay-tile-icon">
                                        <x-icon name="calendar" class="h-5 w-5" />
                                    </div>
                                    <p class="guest-stay-tile-label">Check-In</p>
                                    <p class="guest-stay-tile-date">{{ $booking->check_in_date->format('M d, Y') }}</p>
                                </div>
                                <div class="guest-stay-tile">
                                    <div class="guest-stay-tile-icon">
                                        <x-icon name="calendar" class="h-5 w-5" />
                                    </div>
                                    <p class="guest-stay-tile-label">Check-Out</p>
                                    <p class="guest-stay-tile-date">{{ $booking->check_out_date->format('M d, Y') }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Name --}}
                        <div class="mt-5" id="name-display-block">
                            <p class="text-sm font-bold">Name</p>
                            <div class="mt-2 flex items-center guest-input" style="cursor:default">
                                <span>{{ $booking->guest_name }}</span>
                            </div>
                        </div>
                        <input type="hidden" name="guest_name" value="{{ $booking->guest_name }}">
                        {{-- Phone --}}
                        @if($booking->phone)
                        <div class="mt-5" id="phone-display-block">
                            <p class="text-sm font-bold">Phone number</p>
                            <div class="mt-2 flex items-center justify-between guest-input" style="cursor:default">
                                <span>{{ $booking->formatted_phone }}</span>
                                <button type="button" id="phone-edit-pencil" class="text-slate-400 hover:text-slate-600" title="Edit phone number">
                                    <x-icon name="edit" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <div class="mt-5 hidden" id="phone-input-block">
                            <label class="block text-sm font-bold">Phone number</label>
                            <div class="mt-2 flex gap-2">
                                <div class="relative w-28 shrink-0">
                                    <button type="button" id="guest-phone-country-button" class="guest-input flex w-full items-center justify-between gap-1.5 px-2.5 text-left">
                                        <span id="guest-phone-country-label" class="flex items-center gap-1.5 text-sm font-medium">
                                            <span id="guest-phone-country-flag">🇺🇸</span>
                                            <span id="guest-phone-country-dial">+1</span>
                                        </span>
                                        <span aria-hidden="true" class="text-xs text-slate-400">▾</span>
                                    </button>
                                    <div id="guest-phone-country-menu" class="absolute left-0 top-full z-20 mt-1 hidden w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                        <div class="border-b border-slate-100 p-2">
                                            <input type="text" id="guest-phone-country-search" placeholder="Search country or code" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none" autocomplete="off">
                                        </div>
                                        <div id="guest-phone-country-list" class="max-h-56 overflow-y-auto"></div>
                                    </div>
                                </div>
                                <input type="hidden" id="guest-phone-country-code" name="phone_country_code" value="+1">
                                <input name="phone" type="tel" value="{{ old('phone', $booking->phone) }}" placeholder="(555) 000-0000" autocomplete="tel" class="guest-input flex-1 min-w-0">
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">We will not share your mobile information with third parties for marketing purposes.</p>
                        </div>
                        @else
                        <div class="mt-5">
                            <label class="block text-sm font-bold">Phone number</label>
                            <div class="mt-2 flex gap-2">
                                <div class="relative w-28 shrink-0">
                                    <button type="button" id="guest-phone-country-button" class="guest-input flex w-full items-center justify-between gap-1.5 px-2.5 text-left">
                                        <span id="guest-phone-country-label" class="flex items-center gap-1.5 text-sm font-medium">
                                            <span id="guest-phone-country-flag">🇺🇸</span>
                                            <span id="guest-phone-country-dial">+1</span>
                                        </span>
                                        <span aria-hidden="true" class="text-xs text-slate-400">▾</span>
                                    </button>
                                    <div id="guest-phone-country-menu" class="absolute left-0 top-full z-20 mt-1 hidden w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                        <div class="border-b border-slate-100 p-2">
                                            <input type="text" id="guest-phone-country-search" placeholder="Search country or code" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 focus:border-slate-400 focus:outline-none" autocomplete="off">
                                        </div>
                                        <div id="guest-phone-country-list" class="max-h-56 overflow-y-auto"></div>
                                    </div>
                                </div>
                                <input type="hidden" id="guest-phone-country-code" name="phone_country_code" value="+1">
                                <input name="phone" type="tel" value="{{ old('phone') }}" placeholder="(555) 000-0000" autocomplete="tel" required class="guest-input flex-1 min-w-0">
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">We will not share your mobile information with third parties for marketing purposes.</p>
                        </div>
                        @endif

                        {{-- Email --}}
                        @if($booking->email)
                        <div class="mt-7" id="email-display-block">
                            <p class="text-sm font-bold">Email address</p>
                            <div class="mt-2 flex items-center justify-between guest-input" style="cursor:default">
                                <span>{{ $booking->email }}</span>
                                <button type="button" id="email-edit-pencil" class="text-slate-400 hover:text-slate-600" title="Edit email address">
                                    <x-icon name="edit" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <label class="mt-7 hidden block text-sm font-bold" id="email-input-block">
                            Email address
                            <input name="email" type="email" value="{{ old('email', $booking->email) }}" placeholder="name@example.com" autocomplete="email" class="guest-input @error('email') border-red-400 @enderror" aria-describedby="@error('email') email-error @enderror">
                            @error('email')
                                <span id="email-error" class="guest-field-error">{{ $message }}</span>
                            @enderror
                        </label>
                        @else
                        <label class="mt-7 block text-sm font-bold">
                            Email address
                            <input name="email" type="email" value="{{ old('email') }}" required placeholder="name@example.com" autocomplete="email" class="guest-input @error('email') border-red-400 @enderror" aria-describedby="@error('email') email-error @enderror">
                            @error('email')
                                <span id="email-error" class="guest-field-error">{{ $message }}</span>
                            @enderror
                        </label>
                        @endif

                        {{-- Parking --}}
                        @if(is_null($booking->parking_needed))
                        <div class="mt-5">
                            <p class="text-sm font-bold">Will You Have A Vehicle?</p>
                            <div id="parking-question-block" class="mt-3 grid grid-cols-2 gap-3">
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold hover:bg-slate-50">
                                    <input type="radio" name="parking_needed" value="1" class="accent-blue-600">
                                    Yes I Need Parking
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm font-semibold hover:bg-slate-50">
                                    <input type="radio" name="parking_needed" value="0" class="accent-blue-600">
                                    No I Will Not Need Parking
                                </label>
                            </div>
                            <span id="parking-error" class="guest-field-error" style="display:none">Please let us know if you'll be parking.</span>
                        </div>
                        @endif

                        {{-- Check-in time --}}
                        <div class="mt-5">
                            <label class="text-sm font-bold">What time are you planning to check in? <span class="text-red-600">*</span>
                                <select name="checkin_time_preference" id="checkin_time_preference_select" class="guest-input mt-2 @error('checkin_time_preference') border-red-400 @enderror" aria-describedby="checkin-time-error">
                                    <option value="" disabled {{ old('checkin_time_preference', $booking->checkin_time_preference) ? '' : 'selected' }}>Select a time</option>
                                    @foreach($checkinTimeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('checkin_time_preference', $booking->checkin_time_preference) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <span id="checkin-time-error" class="guest-field-error" style="display:@error('checkin_time_preference')block @else none @enderror">
                                @error('checkin_time_preference'){{ $message }}@else Please select a check-in time. @enderror
                            </span>
                            <p class="mt-1 text-xs text-slate-400">Check in time is 4pm, we will try our best to accommodate early check in if desired, and then update you if available.</p>
                        </div>

                        {{-- Check-out time --}}
                        <div class="mt-5">
                            <label class="text-sm font-bold">What time are you planning to check out? <span class="text-red-600">*</span>
                                <select name="checkout_time_preference" id="checkout_time_preference_select" class="guest-input mt-2 @error('checkout_time_preference') border-red-400 @enderror" aria-describedby="checkout-time-error">
                                    <option value="" disabled {{ old('checkout_time_preference', $booking->checkout_time_preference) ? '' : 'selected' }}>Select a time</option>
                                    @foreach($checkoutTimeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('checkout_time_preference', $booking->checkout_time_preference) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <span id="checkout-time-error" class="guest-field-error" style="display:@error('checkout_time_preference')block @else none @enderror">
                                @error('checkout_time_preference'){{ $message }}@else Please select a check-out time. @enderror
                            </span>
                            <p class="mt-1 text-xs text-slate-400">Check out time is 10am, we will try our best to accommodate late check out if desired, and then update you if available.</p>
                        </div>

                        @php
                            $termsUrl = route('legal.terms');
                            $privacyUrl = route('legal.privacy');
                            $rentalContractUrl = route('legal.rental-contract');
                            $needsContractSignature = $idwNeedsContractSignature;
                        @endphp

                        @if(! $booking->terms_accepted_at)
                        <div class="mt-6 rounded-xl border border-slate-200 p-4">
                            <p class="mb-2 text-sm font-semibold text-slate-900">Terms of Service &amp; Privacy Policy </p>
                            <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="terms_accepted" id="terms-accepted-checkbox" value="1" required class="mt-0.5 rounded border-slate-300">
                                <span>I agree to the <a href="{{ $termsUrl }}" class="font-medium underline" target="_blank" rel="noopener">Terms of Service</a> and <a href="{{ $privacyUrl }}" class="font-medium underline" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                            </label>
                            @if($needsContractSignature)
                            <p class="mt-3 text-xs text-slate-500">You'll sign the rental agreement in the next step, right after your ID is verified.</p>
                            @endif
                        </div>
                        @endif

                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-2 text-sm font-semibold text-slate-900">SMS notifications</p>
                            <p class="text-xs leading-5 text-slate-600">{!! \App\Models\Setting::getValue('legal_sms_consent_content', '<p>By checking this box, you agree to receive reservation and access-related text updates from Guest Hub.</p>') !!}</p>
                            <label class="mt-3 flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="sms_consent" id="sms-consent-checkbox" value="1" class="mt-0.5 rounded border-slate-300">
                                <span>Yes, I agree to receive recurring non-marketing text messages from Guest Hub and the host or property manager for my reservation, including reservation confirmations, identity-verification codes, check-in and access instructions, stay and safety alerts, guest-support messages, checkout reminders, and post-stay review requests. Message frequency varies, up to 20 messages per month. Message and data rates may apply. Reply STOP to opt out or HELP for help. Consent is not a condition of booking or purchase. View the <a href="{{ $termsUrl }}" class="font-medium underline" target="_blank" rel="noopener">Terms of Service</a> and <a href="{{ $privacyUrl }}" class="font-medium underline" target="_blank" rel="noopener">Privacy Policy</a>.</span>
                            </label>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <button type="button" id="step1-next-btn" class="guest-primary-btn w-full col-span-2">Agree &amp; Continue</button>
                        </div>
                    </div>

                    {{-- ══════════════════ STEP 2 — ID capture ══════════════════ --}}
                    <div class="idw-step{{ $idwStartStep === 2 ? '' : ' hidden' }}" data-step="2">
                        @php
                            // Only require (and show a capture tile for) a side that's actually
                            // missing — cleared by an admin decline, never uploaded, or the
                            // booking isn't already marked photo_id_received (e.g. captured
                            // outside the guest flow). A decline on one side should never
                            // re-prompt an already-approved other side. Computed unconditionally
                            // here (not just inside the "not yet received" branch below) since
                            // the JS further down references these regardless of which branch
                            // renders.
                            $idwFrontRequired = ! $booking->photo_id_received && blank($booking->photo_id_path);
                            $idwBackRequired = ! $booking->photo_id_received && blank($booking->photo_id_back_path) && $booking->id_type !== 'passport';
                        @endphp
                        @if($booking->photo_id_received)
                        <div class="mt-5 text-center">
                            <div class="guest-big-check mx-auto">
                                <x-icon name="check" class="h-8 w-8" />
                            </div>
                            <p class="mt-4 font-semibold text-slate-950">ID already received</p>
                            <p class="mt-1 text-sm text-slate-500">No need to upload it again, you're all set to continue.</p>
                        </div>
                        @else
                        <div class="mt-5" id="id-capture-section">
                            <p class="text-sm font-bold mb-3">Photo ID <span class="text-red-500">*</span></p>

                            <div id="idw-desktop-notice" class="hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                                <p class="text-sm font-bold text-slate-800">It's easier to snap this on your phone</p>
                                <p class="mt-1 text-xs text-slate-500">Scan the code below with your phone's camera to pick up right where you left off.</p>
                                <div id="idw-qr-canvas" class="mx-auto mt-4 flex items-center justify-center" style="width:180px;height:180px"></div>
                                <p class="mt-3 text-xs font-semibold text-slate-600">Or open this link on your phone:</p>
                                <p id="idw-qr-link" class="mt-1 break-all rounded-lg bg-white px-3 py-2 text-xs font-mono text-slate-700 border border-slate-200"></p>
                                <button type="button" id="idw-continue-desktop-btn" class="mt-4 text-xs font-semibold text-blue-600 underline">Don't have a mobile device? Continue on desktop</button>
                            </div>

                            <div id="idw-mobile-capture-ui">
                            <div id="camera-container" class="relative w-full rounded-xl overflow-hidden bg-black hidden" style="aspect-ratio:16/9">
                                <video id="camera-stream" class="w-full h-full object-cover" autoplay playsinline muted></video>
                                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                    <div class="w-[88%] rounded-lg border-4 border-white" style="aspect-ratio:1.586/1;box-shadow:0 0 0 9999px rgba(0,0,0,0.45)"></div>
                                </div>
                                <p id="camera-instruction-label" class="pointer-events-none absolute top-2 left-0 right-0 text-center text-white text-sm font-bold" style="text-shadow:0 1px 3px rgba(0,0,0,0.8)"></p>
                                <p class="pointer-events-none absolute bottom-2 left-0 right-0 text-center text-white text-xs font-semibold px-3" style="text-shadow:0 1px 3px rgba(0,0,0,0.8)">Use a contrasting background so all edges show</p>
                            </div>
                            <div id="capture-btn-wrapper" class="hidden mt-3 flex flex-col items-center gap-2">
                                <p id="idw-capture-status" class="text-sm font-semibold text-slate-700 text-center">Loading camera…</p>
                            </div>
                            <div id="front-preview-block" class="hidden mt-3">
                                <p class="text-xs font-semibold text-slate-500 mb-1">Front of ID</p>
                                <img id="front-preview" class="w-full rounded-xl object-cover" style="max-height:180px">
                                <p id="front-blur-warning" class="mt-1 hidden text-xs font-semibold text-red-500">Image is blurry. Please retake.</p>
                                <button type="button" id="retake-front-btn" class="mt-2 text-xs font-semibold text-blue-600 underline">{{ $booking->id_type === 'passport' ? 'Retake' : 'Retake front' }}</button>
                            </div>
                            <div id="back-preview-block" class="hidden mt-3">
                                <p class="text-xs font-semibold text-slate-500 mb-1">Back of ID</p>
                                <img id="back-preview" class="w-full rounded-xl object-cover" style="max-height:180px">
                                <p id="back-blur-warning" class="mt-1 hidden text-xs font-semibold text-red-500">Image is blurry. Please retake.</p>
                                <button type="button" id="retake-back-btn" class="mt-2 text-xs font-semibold text-blue-600 underline">Retake back</button>
                            </div>
                            <div id="upload-zone-trigger" class="guest-upload guest-upload-id mt-3 cursor-pointer{{ $booking->id_type === 'passport' ? ' is-passport' : '' }}{{ $idwFrontRequired ? '' : ' hidden' }}" onclick="startCamera('front')">
                                @if($booking->id_type === 'passport')
                                <img src="{{ asset('id_icons/passportID.png') }}" alt="Passport example">
                                @else
                                <img src="{{ asset('id_icons/frontID.jpg') }}" alt="Front of ID example">
                                @endif
                            </div>
                            @if($booking->id_type === 'passport')
                            <p id="upload-zone-trigger-front-label" class="mt-2 text-center font-bold{{ $idwFrontRequired ? '' : ' hidden' }}">Tap to take photo of passport data page</p>
                            @else
                            <p id="upload-zone-trigger-front-label" class="mt-2 text-center font-bold{{ $idwFrontRequired ? '' : ' hidden' }}">Tap to take photo of front of ID</p>
                            @endif
                            <div id="upload-zone-trigger-back" class="guest-upload guest-upload-id mt-3 cursor-pointer{{ $idwBackRequired && ! $idwFrontRequired ? '' : ' hidden' }}" onclick="startCamera('back')">
                                <img src="{{ asset('id_icons/backID.jpg') }}" alt="Back of ID example">
                            </div>
                            <p id="upload-zone-trigger-back-label" class="mt-2 text-center font-bold{{ $idwBackRequired && ! $idwFrontRequired ? '' : ' hidden' }}">Tap to take photo of back of ID</p>
                            <input type="hidden" name="photo_id" id="photo-id-data">
                            <input type="hidden" name="photo_id_back" id="photo-id-back-data">
                            </div>
                        </div>
                        @endif

                        <div class="mt-6 grid grid-cols-2 gap-3" id="id-capture-actions">
                            <button type="button" class="guest-outline-btn w-full" data-prev="1">Back</button>
                            <button type="button" class="guest-primary-btn w-full" id="id-capture-next-btn">Next</button>
                        </div>
                        <p class="mt-3 text-center text-xs leading-5 text-slate-500">Your information is used only for secure check-in verification.</p>

                        {{-- Revealed by JS only once the ID has been uploaded and scanned
                             (verified) — signing before that point is no longer possible,
                             since we can't check the typed name against the ID until then. --}}
                        @if($idwNeedsContractSignature)
                        <div id="agreement-sign-panel" class="mt-6 rounded-xl border border-slate-200 p-4 hidden">
                            <p class="mb-2 text-sm font-semibold text-slate-900">Your ID is verified. Sign your rental agreement to continue.</p>
                            <label class="field-label" for="agreement-sign-name">Type your full legal name exactly as it appears on your government ID <span class="text-red-600">*</span></label>
                            <input id="agreement-sign-name" type="text" class="guest-input mt-2" autocomplete="off" spellcheck="false" placeholder="Full legal name">
                            <span id="agreement-sign-error" class="guest-field-error" style="display:none">This must match your name exactly as it appears on your government ID.</span>
                            <button type="button" class="guest-primary-btn w-full mt-4" id="agreement-sign-btn">Sign &amp; Continue</button>
                        </div>
                        @endif
                    </div>

                    {{-- ══════════════════ STEP 3 — Smart lock / August Home ══════════════════ --}}
                    <div class="idw-step hidden" data-step="3">
                        <div class="mt-5 text-center">
                            <p class="font-semibold text-slate-950">Smart Lock Access</p>
                            <div class="mt-1 text-sm text-slate-500">{!! \App\Models\Setting::getValue('lock_message', "If you'd like quicker access to the unit, you can download the August Home app.") !!}</div>
                        </div>
                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <button type="button" class="guest-outline-btn w-full" data-prev="2">Back</button>
                            <button type="button" id="smart-lock-continue-btn" class="guest-primary-btn w-full">Next</button>
                        </div>
                    </div>

                </form>

                {{-- Runs immediately after the steps are parsed (before paint)
                     so a reload restores the saved step without flashing the
                     welcome screen. --}}
                <script>
                (function () {
                    try {
                        var step = "{{ $idwStartStep }}";
                        try {
                            var saved = JSON.parse(sessionStorage.getItem("idw_form_state_{{ $booking->booking_id }}") || "null");
                            if (saved && saved.step !== undefined && saved.step !== null) {
                                step = String(saved.step);
                            }
                        } catch (e) {}

                        // Never leave the guest on the final "get the August app"
                        // step while their ID still needs uploading/re-uploading.
                        var needsId = {{ $idwNeedsId ? 'true' : 'false' }};
                        var detailsComplete = {{ $idwDetailsComplete ? 'true' : 'false' }};
                        // Step 0 belonged to the removed welcome screen. Reset
                        // any session state created by that older flow to the
                        // first form step.
                        if (step === "0") {
                            step = "1";
                        }
                        if (!detailsComplete && step !== "1") {
                            step = "1";
                        }
                        if (needsId && step === "3") {
                            step = "2";
                        }

                        document.querySelectorAll(".idw-step").forEach(function (s) {
                            s.classList.toggle("hidden", s.getAttribute("data-step") !== step);
                        });
                        var indicator = document.getElementById("step-indicator-wrapper");
                        if (indicator) indicator.classList.remove("hidden");
                        document.querySelectorAll(".step-num").forEach(function (el) {
                            el.classList.toggle("is-current", el.getAttribute("data-num") === step);
                        });
                    } catch (e) {}
                })();
                </script>

                <script src="{{ asset('js/guest-phone-country.js') }}"></script>
                <script>
                    (function () {
                        document.querySelectorAll('input[name="phone"]').forEach(function (phoneInput) {
                            phoneInput.addEventListener('input', function (e) {
                                var digits = e.target.value.replace(/\D/g, '').slice(0, 10);
                                var formatted = digits;
                                if (digits.length > 6) {
                                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                                } else if (digits.length > 3) {
                                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                                } else if (digits.length > 0) {
                                    formatted = '(' + digits;
                                }
                                e.target.value = formatted;
                            });
                        });
                    })();
                </script>
                <script>
                    (function () {
                        document.querySelectorAll('input[name="phone"]').forEach(function (phoneInput) {
                            phoneInput.addEventListener('input', function (e) {
                                var digits = e.target.value.replace(/\D/g, '').slice(0, 10);
                                var formatted = digits;
                                if (digits.length > 6) {
                                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
                                } else if (digits.length > 3) {
                                    formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                                } else if (digits.length > 0) {
                                    formatted = '(' + digits;
                                }
                                e.target.value = formatted;
                            });
                        });
                    })();
                </script>
                <script>
                (function() {
                    var steps = document.querySelectorAll(".idw-step");
                    var stepNums = document.querySelectorAll(".step-num");

                    function goToStep(n) {
                        steps.forEach(function(s) {
                            s.classList.toggle("hidden", s.getAttribute("data-step") !== String(n));
                        });
                        stepNums.forEach(function(el) {
                            el.classList.toggle("is-current", el.getAttribute("data-num") === String(n));
                        });
                        var indicatorWrapper = document.getElementById("step-indicator-wrapper");
                        if (indicatorWrapper) {
                            indicatorWrapper.classList.remove("hidden");
                        }
                        idwSaveState({ step: String(n) });
                    }
                    window.goToStep = goToStep;

                    var IDW_STORAGE_KEY = "idw_form_state_{{ $booking->booking_id }}";

                    function idwSaveState(partial) {
                        try {
                            var current = JSON.parse(sessionStorage.getItem(IDW_STORAGE_KEY) || "{}");
                            var merged = Object.assign(current, partial);
                            sessionStorage.setItem(IDW_STORAGE_KEY, JSON.stringify(merged));
                        } catch (_) {}
                    }
                    window.idwSaveState = idwSaveState;

                    function idwClearState() {
                        try {
                            sessionStorage.removeItem(IDW_STORAGE_KEY);
                            sessionStorage.removeItem("idw_id_rejection_seen_{{ $booking->booking_id }}");
                        } catch (_) {}
                    }
                    window.idwClearState = idwClearState;

                    function idwRestoreState() {
                        var saved;
                        try { saved = JSON.parse(sessionStorage.getItem(IDW_STORAGE_KEY) || "null"); } catch (_) { saved = null; }
                        if (!saved) {
                            @if($idwDetailsComplete && ($booking->needsIdApproval() || $booking->guest_authenticated_at))
                                goToStep(2);
                            @endif
                            return false;
                        }
                        if (String(saved.step) === "0") {
                            saved.step = "1";
                        }
                        if (!{{ $idwDetailsComplete ? 'true' : 'false' }} && String(saved.step) !== "1") {
                            saved.step = "1";
                        }

                        var fieldNames = ["guest_name", "phone", "email", "checkin_time_preference", "checkout_time_preference"];
                        fieldNames.forEach(function(name) {
                            if (saved[name] === undefined) return;
                            var input = document.querySelector('[name="' + name + '"]');
                            if (input) input.value = saved[name];
                        });
                        if (saved.parking_needed !== undefined) {
                            var radio = document.querySelector('input[name="parking_needed"][value="' + saved.parking_needed + '"]');
                            if (radio) radio.checked = true;
                        }
                        var photoIdEl = document.getElementById("photo-id-data");
                        if (saved.photo_id && photoIdEl) {
                            photoIdEl.value = saved.photo_id;
                            var frontImg = document.getElementById("front-preview");
                            var uploadTrigger = document.getElementById("upload-zone-trigger");
                            var uploadTriggerLabel = document.getElementById("upload-zone-trigger-front-label");
                            var uploadTriggerBack = document.getElementById("upload-zone-trigger-back");
                            var uploadTriggerBackLabel = document.getElementById("upload-zone-trigger-back-label");
                            if (frontImg) {
                                frontImg.src = saved.photo_id;
                                document.getElementById("front-preview-block").classList.remove("hidden");
                                if (uploadTrigger) uploadTrigger.classList.add("hidden");
                                if (uploadTriggerLabel) uploadTriggerLabel.classList.add("hidden");
                                if (idwBackRequired && (saved.photo_id_back || isPassportGlobal())) {
                                    if (uploadTriggerBack) uploadTriggerBack.classList.remove("hidden");
                                    if (uploadTriggerBackLabel) uploadTriggerBackLabel.classList.remove("hidden");
                                }
                            }
                        }
                        var photoIdBackEl = document.getElementById("photo-id-back-data");
                        if (saved.photo_id_back && photoIdBackEl) {
                            photoIdBackEl.value = saved.photo_id_back;
                            var backImg = document.getElementById("back-preview");
                            if (backImg) {
                                backImg.src = saved.photo_id_back;
                                document.getElementById("back-preview-block").classList.remove("hidden");
                            }
                        }
                        if (saved.step) {
                            var restoreStep = String(saved.step);
                            var needsIdRestore = {{ $idwNeedsId ? 'true' : 'false' }};
                            if (needsIdRestore && restoreStep === "3") {
                                restoreStep = "2";
                            }
                            goToStep(restoreStep);
                        }
                        return true;
                    }

                    function isPassportGlobal() {
                        return document.getElementById("upload-zone-trigger") &&
                            document.getElementById("upload-zone-trigger").classList.contains("is-passport");
                    }

                    document.querySelectorAll("[data-next]:not(#id-capture-next-btn)").forEach(function(btn) {
                        btn.addEventListener("click", function() {
                            var step = btn.closest(".idw-step");
                            if (step && step.querySelector("input:invalid")) {
                                var invalid = step.querySelector("input:invalid");
                                invalid.reportValidity();
                                return;
                            }
                            if (step) {
                                var fieldChecks = [
                                    { name: "guest_name", label: "your name" },
                                    { name: "phone", label: "your phone number" },
                                    { name: "email", label: "your email address" }
                                ];
                                for (var fc = 0; fc < fieldChecks.length; fc++) {
                                    var visibleInputs = Array.prototype.filter.call(
                                        step.querySelectorAll('input[name="' + fieldChecks[fc].name + '"]'),
                                        function(el) { return el.offsetParent !== null; }
                                    );
                                    var activeInput = visibleInputs[0];
                                    if (activeInput && !activeInput.value.trim()) {
                                        activeInput.classList.add("border-red-400");
                                        activeInput.scrollIntoView({ behavior: "smooth", block: "center" });
                                        activeInput.focus();
                                        return;
                                    } else if (activeInput) {
                                        activeInput.classList.remove("border-red-400");
                                    }
                                }
                                var parkingGroup = step.querySelectorAll('input[name="parking_needed"]');
                                var parkingError = document.getElementById("parking-error");
                                if (parkingGroup.length) {
                                    var parkingChecked = Array.prototype.some.call(parkingGroup, function(r) { return r.checked; });
                                    if (!parkingChecked) {
                                        if (parkingError) parkingError.style.display = "block";
                                        var parkingBlock = document.getElementById("parking-question-block");
                                        if (parkingBlock) parkingBlock.scrollIntoView({ behavior: "smooth", block: "center" });
                                        return;
                                    } else if (parkingError) {
                                        parkingError.style.display = "none";
                                    }
                                }
                                var timeSelect = step.querySelector('#checkin_time_preference_select');
                                if (timeSelect && !timeSelect.value) {
                                    timeSelect.classList.add("border-red-400");
                                    var timeError = document.getElementById("checkin-time-error");
                                    if (timeError) timeError.style.display = "block";
                                    timeSelect.scrollIntoView({ behavior: "smooth", block: "center" });
                                    timeSelect.focus();
                                    return;
                                }
                                var checkoutSelect = step.querySelector('#checkout_time_preference_select');
                                if (checkoutSelect && !checkoutSelect.value) {
                                    checkoutSelect.classList.add("border-red-400");
                                    var checkoutError = document.getElementById("checkout-time-error");
                                    if (checkoutError) checkoutError.style.display = "block";
                                    checkoutSelect.scrollIntoView({ behavior: "smooth", block: "center" });
                                    checkoutSelect.focus();
                                    return;
                                }
                            }
                            goToStep(btn.getAttribute("data-next"));
                        });
                    });

                    document.querySelectorAll("[data-prev]").forEach(function(btn) {
                        btn.addEventListener("click", function() {
                            goToStep(btn.getAttribute("data-prev"));
                        });
                    });

                    var phonePencil = document.getElementById("phone-edit-pencil");
                    if (phonePencil) {
                        phonePencil.addEventListener("click", function() {
                            document.getElementById("phone-display-block").classList.add("hidden");
                            document.getElementById("phone-input-block").classList.remove("hidden");
                        });
                    }



                    var emailPencil = document.getElementById("email-edit-pencil");
                    if (emailPencil) {
                        emailPencil.addEventListener("click", function() {
                            document.getElementById("email-display-block").classList.add("hidden");
                            document.getElementById("email-input-block").classList.remove("hidden");
                        });
                    }

                    ["guest_name", "phone", "email", "checkin_time_preference", "checkout_time_preference"].forEach(function(name) {
                        var input = document.querySelector('[name="' + name + '"]');
                        if (input) {
                            input.addEventListener("input", function() {
                                var partial = {};
                                partial[name] = input.value;
                                idwSaveState(partial);
                            });
                            input.addEventListener("change", function() {
                                var partial = {};
                                partial[name] = input.value;
                                idwSaveState(partial);
                            });
                        }
                    });
                    document.querySelectorAll('input[name="parking_needed"]').forEach(function(radio) {
                        radio.addEventListener("change", function() {
                            idwSaveState({ parking_needed: radio.value });
                            var vehicleBlock = document.getElementById("vehicle-info-block");
                            if (vehicleBlock) vehicleBlock.style.display = radio.value === "1" && radio.checked ? "" : "none";
                        });
                    });

                    try { idwRestoreState(); } catch (e) { console.error("idwRestoreState failed:", e); }
                })();

                var currentSide = "front";
                var stream = null;
                var idwCaptureGeneration = 0;
                var photoIdRequired = {{ $booking->photo_id_received ? 'false' : 'true' }};
                var isPassport = {{ $booking->id_type === 'passport' ? 'true' : 'false' }};
                // Which side(s) actually need a (re)capture right now — a side that's
                // already approved (or not cleared by a decline) should never be
                // re-prompted, even after the guest finishes capturing the other side.
                var idwFrontRequired = {{ $idwFrontRequired ? 'true' : 'false' }};
                var idwBackRequired = {{ $idwBackRequired ? 'true' : 'false' }};

                // ── Device detection (mobile/tablet vs desktop) ────────────────────
                // Deliberately NOT a screen-width check — that's trivially spoofed by
                // resizing a desktop browser window. Instead this checks the actual
                // device/browser signals: the modern userAgentData.mobile flag where
                // available, falling back to a User-Agent match for older browsers,
                // plus a special case for iPads — they report a desktop "Macintosh"
                // UA by default, but a real Mac never reports multi-touch, so
                // platform === 'MacIntel' + maxTouchPoints > 1 reliably catches them.
                function idwIsMobileOrTablet() {
                    try {
                        if (navigator.userAgentData && typeof navigator.userAgentData.mobile === "boolean") {
                            if (navigator.userAgentData.mobile) return true;
                        }
                        var ua = navigator.userAgent || "";
                        if (/Android|iPhone|iPod|iPad|Mobile|Tablet/i.test(ua)) return true;
                        if (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1) return true;
                        return false;
                    } catch (e) {
                        // If detection throws for any reason, don't block a real guest —
                        // default to showing the camera flow.
                        return true;
                    }
                }

                // ── QR handoff for desktop guests ───────────────────────────────────
                // Photo capture only makes sense on a device with a camera in hand, so
                // desktop guests are pointed to their phone instead of being shown a
                // camera UI. Loaded lazily and only for desktop guests. Generated
                // entirely client-side — no external service call, no cost, and it
                // still works if this CDN is ever unreachable (the plain link below
                // the code is always shown as a fallback).
                var IDW_QRCODE_SRC = "https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js";
                var idwQrLoadStarted = false;

                function idwShowDesktopNotice() {
                    document.getElementById("idw-mobile-capture-ui").classList.add("hidden");
                    document.getElementById("idw-desktop-notice").classList.remove("hidden");
                    document.getElementById("idw-qr-link").textContent = window.location.href;

                    if (idwQrLoadStarted) return;
                    idwQrLoadStarted = true;
                    var script = document.createElement("script");
                    script.src = IDW_QRCODE_SRC;
                    script.onload = function() {
                        try {
                            new QRCode(document.getElementById("idw-qr-canvas"), {
                                text: window.location.href,
                                width: 180,
                                height: 180,
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        } catch (e) {
                            // QR render failed — the plain link underneath still works.
                        }
                    };
                    document.head.appendChild(script);
                }

                (function idwInitDeviceGate() {
                    if (!photoIdRequired) return;
                    if (!idwIsMobileOrTablet()) {
                        idwShowDesktopNotice();
                    }
                    var continueBtn = document.getElementById("idw-continue-desktop-btn");
                    if (continueBtn) {
                        continueBtn.addEventListener("click", function() {
                            document.getElementById("idw-desktop-notice").classList.add("hidden");
                            document.getElementById("idw-mobile-capture-ui").classList.remove("hidden");
                        });
                    }
                })();

                // ── OpenCV.js loader ────────────────────────────────────────────────
                // OpenCV.js is loaded lazily (only once the guest reaches this step) and
                // runs entirely client-side in the browser — this has no server/hosting
                // requirements at all (works the same on any host, cPanel included).
                // If it fails to load (blocked network, offline, slow connection) we fall
                // back to the old manual tap-to-capture button + JS blur check so nobody
                // gets stuck unable to upload their ID.
                var IDW_OPENCV_SRC = "https://docs.opencv.org/4.9.0/opencv.js";
                var idwCvReady = false;
                var idwCvFailed = false;
                var idwCvLoadStarted = false;

                function loadOpenCv(onReady, onFail) {
                    if (idwCvReady) { onReady(); return; }
                    if (idwCvFailed) { onFail(); return; }
                    if (!idwCvLoadStarted) {
                        idwCvLoadStarted = true;
                        var script = document.createElement("script");
                        script.src = IDW_OPENCV_SRC;
                        script.async = true;
                        script.onerror = function() { idwCvFailed = true; onFail(); };
                        document.head.appendChild(script);
                        // Give it a reasonable timeout — slow connections shouldn't block
                        // the guest from uploading their ID indefinitely.
                        setTimeout(function() {
                            if (!idwCvReady && !idwCvFailed) { idwCvFailed = true; onFail(); }
                        }, 8000);
                    }
                    var checkInterval = setInterval(function() {
                        if (window.cv && window.cv.Mat) {
                            // Some builds need onRuntimeInitialized; guard for both cases.
                            if (window.cv.onRuntimeInitialized !== undefined && !idwCvReady) {
                                window.cv["onRuntimeInitialized"] = function() {
                                    idwCvReady = true;
                                    clearInterval(checkInterval);
                                    onReady();
                                };
                            } else if (!idwCvReady) {
                                idwCvReady = true;
                                clearInterval(checkInterval);
                                onReady();
                            }
                        }
                    }, 100);
                }

                /*
                // ── On-device OCR (Tesseract.js) ───────────────────────────────
                // Reads the text off the captured ID in the browser, so the guest
                // gets "we read your ID" feedback before submitting, and the raw
                // text is sent to the server to verify name/DOB/expiry even when
                // the cloud Vision provider is down. Loaded lazily like OpenCV.
                var IDW_TESSERACT_SRC = "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js";
                var idwTesseractReady = false;
                var idwTesseractLoading = false;
                var idwTesseractFailed = false;

                function loadTesseract(onReady, onFail) {
                    if (window.Tesseract) { idwTesseractReady = true; onReady(); return; }
                    if (idwTesseractFailed) { if (onFail) onFail(); return; }
                    if (!idwTesseractLoading) {
                        idwTesseractLoading = true;
                        var s = document.createElement("script");
                        s.src = IDW_TESSERACT_SRC;
                        s.async = true;
                        s.onload = function() { idwTesseractReady = true; onReady(); };
                        s.onerror = function() { idwTesseractLoading = false; idwTesseractFailed = true; if (onFail) onFail(); };
                        document.head.appendChild(s);
                    }
                    setTimeout(function() {
                        if (!idwTesseractReady && !idwTesseractFailed) {
                            idwTesseractFailed = true;
                            if (onFail) onFail();
                        }
                    }, 8000);
                }

                // Crops the captured photo down to roughly the bottom band where a
                // passport's machine-readable zone (MRZ) sits -- the two/three fixed
                // width OCR-B lines at the very bottom of the data page. Cropping out
                // the header text, photo, and other printed fields before OCR is what
                // actually makes Tesseract usable here: without this it tries to read
                // everything on the page (headers, photo edges, etc.) and produces
                // mostly noise. A standard passport data page (ID-3, ISO/IEC 7810) has
                // its MRZ occupying about the bottom 20% of the page height, spanning
                // nearly the full width.
                // Wide, generous crop of the bottom third of the page -- deliberately
                // NOT tuned tight to the MRZ, since exact MRZ position varies enough
                // across countries' passport layouts and capture angles that a tight
                // crop risks cutting off the name line. Grabbing extra blank margin
                // above the MRZ costs OCR almost nothing; cutting off the name line
                // loses the one field we most need.
                function __idwCropBand(dataUrl, topFraction, cb) {
                    var img = new Image();
                    img.onload = function() {
                        var bandTop = Math.round(img.naturalHeight * topFraction);
                        var bandHeight = img.naturalHeight - bandTop;
                        var canvas = document.createElement("canvas");
                        var scale = 1.5;
                        canvas.width = Math.round(img.naturalWidth * scale);
                        canvas.height = Math.round(bandHeight * scale);
                        var ctx = canvas.getContext("2d");
                        ctx.drawImage(
                            img,
                            0, bandTop, img.naturalWidth, bandHeight,
                            0, 0, canvas.width, canvas.height
                        );
                        cb(canvas.toDataURL("image/jpeg", 0.95));
                    };
                    img.onerror = function() { cb(dataUrl); };
                    img.src = dataUrl;
                }

                // A single fixed "bottom 35%" crop clips the MRZ entirely whenever the
                // guest's capture doesn't perfectly fill the guide box (common -- the
                // guide overlay is decorative, nothing stops the document sitting
                // higher/lower in frame). Try a tight crop first (best signal-to-noise
                // when it lands correctly), and only pay for a second, wider OCR pass
                // if the tight one didn't actually find MRZ-shaped text.
                function __idwLooksLikeMrz(text) {
                    if (!text) return false;
                    var cleaned = text.toUpperCase().replace(/\s+/g, "");
                    // Two ICAO-style anchors: a long run of MRZ filler chars, and the
                    // DOB+check+sex+expiry digit signature used server-side too.
                    return /[A-Z0-9<]{20,}/.test(cleaned) && /\d{6}\d[MF<]\d{6}/.test(cleaned);
                }

                function __idwCropToBottomThird(dataUrl, cb) {
                    __idwCropBand(dataUrl, 0.65, cb);
                }

                // Hard ceiling on the whole OCR attempt. loadTesseract() already times
                // out script *loading* at 8s, but the actual recognize() call has no
                // ceiling of its own -- a stalled worker (slow device, huge image,
                // WASM init hiccup) can hang indefinitely with nothing to fall back to,
                // which is what leaves the guest stuck on "Reading your ID..." forever.
                var IDW_OCR_HARD_TIMEOUT_MS = 20000;

                function ocrIdImage(dataUrl, cb) {
                    var called = false;
                    var hardTimer = setTimeout(function() { finish(null); }, IDW_OCR_HARD_TIMEOUT_MS);
                    function finish(text) {
                        if (called) return;
                        called = true;
                        clearTimeout(hardTimer);
                        cb(text);
                    }

                    // The "mrz" language model (bundled at /tessdata/mrz.traineddata,
                    // BSD-3 licensed, trained by DoubangoTelecom specifically on the
                    // OCR-B font MRZs use) reads the MRZ character set far more
                    // reliably than the generic "eng" prose model -- "eng" was never
                    // trained on this font, which is the root cause behind most of the
                    // "<" -> random-letter misreads we've been patching around in the
                    // parser. Only use it for the whitelisted MRZ-crop passes; the
                    // full-page fallback pass (labels like "DOB", "EXP" on state IDs)
                    // stays on "eng" since that's prose text, not MRZ.
                    function recognizeOne(image, whitelist, onDone, useMrzModel) {
                        var done = false;
                        function once(v) { if (done) return; done = true; onDone(v); }
                        loadTesseract(function() {
                            if (!window.Tesseract || !window.Tesseract.recognize) { once(null); return; }
                            var opts = { logger: null };
                            if (whitelist) opts.tessedit_char_whitelist = whitelist;
                            var lang = "eng";
                            if (useMrzModel) {
                                lang = "mrz";
                                opts.langPath = "/tessdata";
                            }
                            window.Tesseract.recognize(image, lang, opts)
                                .then(function(r) { once(r && r.data && r.data.text ? r.data.text : null); })
                                .catch(function() {
                                    // If the mrz model fails to load (e.g. static asset
                                    // missing), fall back to eng rather than losing the
                                    // read entirely.
                                    if (useMrzModel) {
                                        window.Tesseract.recognize(image, "eng", { logger: null, tessedit_char_whitelist: whitelist })
                                            .then(function(r) { once(r && r.data && r.data.text ? r.data.text : null); })
                                            .catch(function() { once(null); });
                                    } else {
                                        once(null);
                                    }
                                });
                        }, function() { once(null); });
                    }

                    if (!isPassport) {
                        recognizeOne(dataUrl, null, finish);
                        return;
                    }

                    // Passports: run OCR twice and combine the text -- once on a wide
                    // bottom-third crop with the MRZ character set (best chance at a
                    // clean MRZ read regardless of exact line position), and once on
                    // the full page with no restriction (catches printed labelled
                    // fields, and acts as a fallback if the crop missed the MRZ
                    // entirely). The scanner on the server tries multiple parsing
                    // strategies against whatever text it's given, so combining
                    // sources here only helps -- it can't produce a false positive,
                    // it just gives it more to work with.
                    var mrzWhitelist = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<";
                    __idwCropBand(dataUrl, 0.65, function(tightCrop) {
                        recognizeOne(tightCrop, mrzWhitelist, function(tightText) {
                            if (__idwLooksLikeMrz(tightText)) {
                                // Good MRZ signal from the tight crop; still grab the
                                // full-page pass in parallel for printed-label fallback.
                                recognizeOne(dataUrl, null, function(fullText) {
                                    finish([tightText, fullText].filter(Boolean).join("\n"));
                                });
                                return;
                            }
                            // Tight crop missed it -- retake with a wider band (bottom
                            // half) in case the document sat higher in frame than the
                            // guide box assumed, plus the full-page fallback.
                            __idwCropBand(dataUrl, 0.45, function(wideCrop) {
                                var results = [tightText];
                                var remaining = 2;
                                function maybeFinish() {
                                    remaining--;
                                    if (remaining === 0) finish(results.filter(Boolean).join("\n"));
                                }
                                recognizeOne(wideCrop, mrzWhitelist, function(t) { results.push(t); maybeFinish(); }, true);
                                recognizeOne(dataUrl, null, function(t) { results.push(t); maybeFinish(); });
                            });
                        }, true);
                    });
                }

                */

                // Downsamples the captured image, computes a Laplacian-based sharpness
                // score (real focus/blur detection, not just brightness contrast), and a
                // grid edge-density heuristic as a lightweight (non-OCR) "is there
                // text-like structure here" signal.
                // NOTE: this is the legacy JS-only fallback, used only if OpenCV.js fails
                // to load. When OpenCV is available, idwCvCheckFrame() below is used
                // instead — it's faster and considerably more reliable.
                var IDW_DEBUG_LOG_SCORES = false; // tuning logs disabled

                function __idwGetGrayscaleSample(imgEl, targetWidth) {
                    var scale = targetWidth / imgEl.naturalWidth;
                    var w = targetWidth;
                    var h = Math.max(1, Math.round(imgEl.naturalHeight * scale));
                    var canvas = document.createElement("canvas");
                    canvas.width = w;
                    canvas.height = h;
                    var ctx = canvas.getContext("2d");
                    ctx.drawImage(imgEl, 0, 0, w, h);
                    var data = ctx.getImageData(0, 0, w, h).data;
                    var gray = new Float32Array(w * h);
                    for (var i = 0, p = 0; i < data.length; i += 4, p++) {
                        gray[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                    }
                    return { gray: gray, w: w, h: h };
                }

                function __idwLaplacianMap(gray, w, h) {
                    var lap = new Float32Array(w * h);
                    for (var y = 1; y < h - 1; y++) {
                        for (var x = 1; x < w - 1; x++) {
                            var idx = y * w + x;
                            var val =
                                gray[idx - w] + gray[idx + w] + gray[idx - 1] + gray[idx + 1] - 4 * gray[idx];
                            lap[idx] = val;
                        }
                    }
                    return lap;
                }

                function __idwSharpnessVariance(lap, w, h) {
                    var n = w * h;
                    var sum = 0;
                    for (var i = 0; i < n; i++) sum += lap[i];
                    var mean = sum / n;
                    var variance = 0;
                    for (var i = 0; i < n; i++) variance += Math.pow(lap[i] - mean, 2);
                    return variance / n;
                }

                function __idwTextLikeCellRatio(lap, w, h) {
                    var cellSize = 16;
                    var cols = Math.floor(w / cellSize);
                    var rows = Math.floor(h / cellSize);
                    if (cols < 1 || rows < 1) return 0;
                    var textLikeCells = 0;
                    var totalCells = 0;
                    for (var cy = 0; cy < rows; cy++) {
                        for (var cx = 0; cx < cols; cx++) {
                            var edgeCount = 0;
                            var strong = 0;
                            for (var y = cy * cellSize; y < (cy + 1) * cellSize && y < h; y++) {
                                for (var x = cx * cellSize; x < (cx + 1) * cellSize && x < w; x++) {
                                    var v = Math.abs(lap[y * w + x]);
                                    if (v > 15) edgeCount++;
                                    if (v > 60) strong++;
                                }
                            }
                            totalCells++;
                            var cellPixels = cellSize * cellSize;
                            var edgeDensity = edgeCount / cellPixels;
                            if (edgeDensity > 0.12 && edgeDensity < 0.55 && strong < cellPixels * 0.3) {
                                textLikeCells++;
                            }
                        }
                    }
                    return totalCells > 0 ? textLikeCells / totalCells : 0;
                }

                var IDW_SHARPNESS_MIN = 45;      // Laplacian variance floor - retune after real captures
                var IDW_TEXT_RATIO_MIN = 0.03;   // fraction of grid cells that must look text-like

                // Thresholds for the OpenCV auto-capture path — these run on a different
                // scale (full guide-box crop, not a 400px downsample) so they are NOT the
                // same numbers as the legacy JS thresholds above. Untested against real
                // captures yet — retune once real guest photos are reviewed.
                var IDW_CV_FILL_MIN = 0.80;       // detected document must fill ≥80% of the guide box
                var IDW_CV_SHARPNESS_MIN = 120;   // OpenCV Laplacian variance floor
                var IDW_CV_STABLE_FRAMES_NEEDED = 4; // consecutive good frames (~1s) before auto-capture

                function checkBlur(imgEl, warningEl) {
                    var sample = __idwGetGrayscaleSample(imgEl, 400);
                    var lap = __idwLaplacianMap(sample.gray, sample.w, sample.h);
                    var sharpness = __idwSharpnessVariance(lap, sample.w, sample.h);
                    var textRatio = __idwTextLikeCellRatio(lap, sample.w, sample.h);

                    if (IDW_DEBUG_LOG_SCORES) {
                        console.log("[ID capture check] sharpness=" + sharpness.toFixed(2) + " textRatio=" + textRatio.toFixed(3));
                    }

                    if (sharpness < IDW_SHARPNESS_MIN) {
                        warningEl.textContent = "Image is blurry. Please retake.";
                        warningEl.classList.remove("hidden");
                        return false;
                    }
                    if (textRatio < IDW_TEXT_RATIO_MIN) {
                        warningEl.textContent = "No legible ID text detected. Please retake with the ID clearly in frame.";
                        warningEl.classList.remove("hidden");
                        return false;
                    }
                    warningEl.classList.add("hidden");
                    return true;
                }

                // ── OpenCV-based live frame analysis ────────────────────────────────
                // Runs on a downsized crop of the guide-box region while the camera
                // stream is live, every ~250ms. Reports back:
                //   fillRatio  — how much of the guide box the detected document
                //                rectangle occupies (auto-capture requires the ID to
                //                actually fill the frame, not just be present in it)
                //   sharpness  — Laplacian variance (higher = sharper); this uses the
                //                same live frame the guide-box crop will actually use
                //                for the final capture, so by the time we auto-fire,
                //                the frame is already known-sharp (fixes the "haze"
                //                issue caused by capturing before autofocus settles)
                function idwCvCheckFrame(canvas) {
                    var src = cv.imread(canvas);
                    var gray = new cv.Mat();
                    cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

                    // Sharpness via Laplacian variance (OpenCV's own, much faster than
                    // the hand-rolled JS version above).
                    var lap = new cv.Mat();
                    cv.Laplacian(gray, lap, cv.CV_64F);
                    var mean = new cv.Mat();
                    var stddev = new cv.Mat();
                    cv.meanStdDev(lap, mean, stddev);
                    var sharpness = Math.pow(stddev.data64F[0], 2);

                    // Document-fill detection: edge detect, find the largest contour,
                    // compare its bounding box to the full frame.
                    var edges = new cv.Mat();
                    cv.Canny(gray, edges, 50, 150);
                    var contours = new cv.MatVector();
                    var hierarchy = new cv.Mat();
                    cv.findContours(edges, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

                    var frameArea = canvas.width * canvas.height;
                    var bestArea = 0;
                    for (var i = 0; i < contours.size(); i++) {
                        var contour = contours.get(i);
                        var rect = cv.boundingRect(contour);
                        var area = rect.width * rect.height;
                        if (area > bestArea && area < frameArea * 1.02) {
                            bestArea = area;
                        }
                        contour.delete();
                    }
                    var fillRatio = frameArea > 0 ? bestArea / frameArea : 0;

                    src.delete(); gray.delete(); lap.delete(); mean.delete(); stddev.delete();
                    edges.delete(); contours.delete(); hierarchy.delete();

                    return { fillRatio: fillRatio, sharpness: sharpness };
                }

                var idwDetectionTimer = null;
                var idwStableFrameCount = 0;
                var idwAutoCaptureInFlight = false;
                var idwCaptureTimer = null;

                function stopDetectionLoop() {
                    if (idwDetectionTimer) { clearInterval(idwDetectionTimer); idwDetectionTimer = null; }
                    idwStableFrameCount = 0;
                    idwAutoCaptureInFlight = false;
                }

                function startDetectionLoop() {
                    stopDetectionLoop();
                    var video = document.getElementById("camera-stream");
                    var statusEl = document.getElementById("idw-capture-status");
                    var detectCanvas = document.createElement("canvas");

                    idwDetectionTimer = setInterval(function() {
                        if (idwAutoCaptureInFlight || !video.videoWidth) return;
                        var crop = __idwGetGuideCropRect(video);
                        // Downsize for speed — we only need this for detection, not the
                        // final saved image (final capture re-crops at full resolution).
                        var scale = Math.min(1, 260 / crop.w);
                        detectCanvas.width = Math.round(crop.w * scale);
                        detectCanvas.height = Math.round(crop.h * scale);
                        detectCanvas.getContext("2d").drawImage(
                            video, crop.x, crop.y, crop.w, crop.h, 0, 0, detectCanvas.width, detectCanvas.height
                        );

                        var result;
                        try {
                            result = idwCvCheckFrame(detectCanvas);
                        } catch (e) {
                            return; // transient decode error, just skip this frame
                        }

                        if (result.fillRatio >= IDW_CV_FILL_MIN && result.sharpness >= IDW_CV_SHARPNESS_MIN) {
                            idwStableFrameCount++;
                            statusEl.textContent = "Hold steady…";
                        } else if (result.fillRatio < IDW_CV_FILL_MIN) {
                            idwStableFrameCount = 0;
                            statusEl.textContent = "Move the ID closer so it fills the frame";
                        } else {
                            idwStableFrameCount = 0;
                            statusEl.textContent = "Hold steady for a clear photo…";
                        }

                        if (idwStableFrameCount >= IDW_CV_STABLE_FRAMES_NEEDED) {
                            idwAutoCaptureInFlight = true;
                            statusEl.textContent = "Capturing…";
                            performCapture();
                        }
                    }, 250);
                }

                function startCamera(side) {
                    currentSide = side;
                    var container = document.getElementById("camera-container");
                    var btnWrapper = document.getElementById("capture-btn-wrapper");
                    var frontTrigger = document.getElementById("upload-zone-trigger");
                    var backTrigger = document.getElementById("upload-zone-trigger-back");
                    var frontLabel = document.getElementById("upload-zone-trigger-front-label");
                    var backLabel = document.getElementById("upload-zone-trigger-back-label");
                    var activeTrigger = side === "front" ? frontTrigger : backTrigger;

                    frontTrigger.classList.add("hidden");
                    backTrigger.classList.add("hidden");
                    frontLabel.classList.add("hidden");
                    backLabel.classList.add("hidden");

                    activeTrigger.parentNode.insertBefore(container, activeTrigger);
                    activeTrigger.parentNode.insertBefore(btnWrapper, activeTrigger);

                    container.classList.remove("hidden");
                    btnWrapper.classList.remove("hidden");
                    document.getElementById("idw-capture-status").textContent = "Loading camera…";

                    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false })
                        .then(function(s) {
                            stream = s;
                            document.getElementById("camera-stream").srcObject = s;
                            var label;
                            if (side === "front") {
                                label = isPassport ? "Take a picture of the passport data page" : "Take a picture of the front of ID";
                            } else {
                                label = "Take a picture of the back of ID";
                            }
                            document.getElementById("camera-instruction-label").textContent = label;

                            loadOpenCv(function() {
                                document.getElementById("idw-capture-status").textContent = "Position the ID within the frame";
                                startDetectionLoop();
                            }, function() {
                                // OpenCV unavailable — still auto-capture (there is
                                // no manual shutter button): hold for a moment, then
                                // snap automatically.
                                document.getElementById("idw-capture-status").textContent = "Hold steady — capturing automatically…";
                                if (idwCaptureTimer) clearTimeout(idwCaptureTimer);
                                idwCaptureTimer = setTimeout(function() {
                                    idwCaptureTimer = null;
                                    performCapture();
                                }, 3500);
                            });
                        })
                        .catch(function() { alert("Camera access denied. Please allow camera permissions and try again."); });
                }

                function stopCamera() {
                    stopDetectionLoop();
                    if (idwCaptureTimer) { clearTimeout(idwCaptureTimer); idwCaptureTimer = null; }
                    if (stream) { stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; }
                    document.getElementById("camera-container").classList.add("hidden");
                    document.getElementById("capture-btn-wrapper").classList.add("hidden");
                }

                // Crops the capture to the same guide-border rectangle the user sees
                // (CSS overlay is decorative only and has no effect on the raw video
                // frame, so we replicate the object-cover + centered-88%-box math here
                // in native video pixel coordinates).
                function __idwGetGuideCropRect(video) {
                    var containerAspect = 16 / 9;
                    var videoAspect = video.videoWidth / video.videoHeight;
                    var visW, visH, offX, offY;
                    if (videoAspect > containerAspect) {
                        visH = video.videoHeight;
                        visW = visH * containerAspect;
                        offX = (video.videoWidth - visW) / 2;
                        offY = 0;
                    } else {
                        visW = video.videoWidth;
                        visH = visW / containerAspect;
                        offX = 0;
                        offY = (video.videoHeight - visH) / 2;
                    }
                    var guideAspect = 1.586;
                    var guideW = visW * 0.88;
                    var guideH = guideW / guideAspect;
                    if (guideH > visH) { guideH = visH; guideW = guideH * guideAspect; }
                    var guideX = offX + (visW - guideW) / 2;
                    var guideY = offY + (visH - guideH) / 2;
                    return { x: guideX, y: guideY, w: guideW, h: guideH };
                }

                if (photoIdRequired) {
                    function performCapture() {
                        var video = document.getElementById("camera-stream");
                        var crop = __idwGetGuideCropRect(video);
                        var canvas = document.createElement("canvas");
                        canvas.width = crop.w;
                        canvas.height = crop.h;
                        canvas.getContext("2d").drawImage(video, crop.x, crop.y, crop.w, crop.h, 0, 0, crop.w, crop.h);
                        var dataUrl = canvas.toDataURL("image/jpeg", 0.92);
                        var side = currentSide;
                        stopCamera();
                        if (side === "front") {
                            var img = document.getElementById("front-preview");
                            img.src = dataUrl;
                            document.getElementById("front-preview-block").classList.remove("hidden");
                            img.onload = function() {
                                var ok = checkBlur(img, document.getElementById("front-blur-warning"));
                                if (ok) {
                                    document.getElementById("photo-id-data").value = dataUrl;
                                    idwSaveState({ photo_id: dataUrl });
                                    if (!isPassport && idwBackRequired) {
                                        document.getElementById("upload-zone-trigger-back").classList.remove("hidden");
                                        document.getElementById("upload-zone-trigger-back-label").classList.remove("hidden");
                                    }
                                }
                            };
                        } else {
                            var img = document.getElementById("back-preview");
                            img.src = dataUrl;
                            document.getElementById("back-preview-block").classList.remove("hidden");
                            img.onload = function() {
                                var ok = checkBlur(img, document.getElementById("back-blur-warning"));
                                if (ok) { document.getElementById("photo-id-back-data").value = dataUrl; idwSaveState({ photo_id_back: dataUrl }); }
                            };
                        }
                    }

                    document.getElementById("retake-front-btn").addEventListener("click", function() {
                        document.getElementById("front-preview-block").classList.add("hidden");
                        document.getElementById("upload-zone-trigger-back").classList.add("hidden");
                        document.getElementById("upload-zone-trigger-back-label").classList.add("hidden");
                        document.getElementById("photo-id-data").value = "";
                        startCamera("front");
                    });

                    document.getElementById("retake-back-btn").addEventListener("click", function() {
                        document.getElementById("back-preview-block").classList.add("hidden");
                        document.getElementById("photo-id-back-data").value = "";
                        startCamera("back");
                    });
                }

                function resetIdCapture() {
                    var frontData = document.getElementById("photo-id-data");
                    var backData = document.getElementById("photo-id-back-data");
                    if (frontData) frontData.value = "";
                    if (backData) backData.value = "";
                    var frontBlock = document.getElementById("front-preview-block");
                    var backBlock = document.getElementById("back-preview-block");
                    if (frontBlock) frontBlock.classList.add("hidden");
                    if (backBlock) backBlock.classList.add("hidden");
                    var frontTrigger = document.getElementById("upload-zone-trigger");
                    var frontLabel = document.getElementById("upload-zone-trigger-front-label");
                    if (frontTrigger) frontTrigger.classList.remove("hidden");
                    if (frontLabel) frontLabel.classList.remove("hidden");
                }

                function withButtonBusy(btn, busyLabel, fn) {
                    var origHtml = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="ui-spinner"></span><span>' + busyLabel + '</span>';
                    function restore() {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                    }
                    fn(restore);
                }

                // ── Step 1 "Next": validate contact fields, AJAX-save via guest.login, then advance ──
                document.getElementById("step1-next-btn").addEventListener("click", function() {
                    var btn = this;
                    var step = btn.closest(".idw-step");
                    if (step && step.querySelector("input:invalid")) {
                        var invalid = step.querySelector("input:invalid");
                        invalid.reportValidity();
                        return;
                    }
                    var fieldChecks = ["guest_name", "phone", "email"];
                    for (var fc = 0; fc < fieldChecks.length; fc++) {
                        var visibleInputs = Array.prototype.filter.call(
                            step.querySelectorAll('input[name="' + fieldChecks[fc] + '"]'),
                            function(el) { return el.offsetParent !== null; }
                        );
                        var activeInput = visibleInputs[0];
                        if (activeInput && !activeInput.value.trim()) {
                            activeInput.classList.add("border-red-400");
                            activeInput.scrollIntoView({ behavior: "smooth", block: "center" });
                            activeInput.focus();
                            return;
                        } else if (activeInput) {
                            activeInput.classList.remove("border-red-400");
                        }
                    }
                    var parkingGroup = step.querySelectorAll('input[name="parking_needed"]');
                    var parkingError = document.getElementById("parking-error");
                    var parkingChecked2 = step.querySelector('input[name="parking_needed"]:checked');
                    if (parkingGroup.length) {
                        if (!parkingChecked2) {
                            if (parkingError) parkingError.style.display = "block";
                            var parkingBlock = document.getElementById("parking-question-block");
                            if (parkingBlock) parkingBlock.scrollIntoView({ behavior: "smooth", block: "center" });
                            return;
                        } else if (parkingError) {
                            parkingError.style.display = "none";
                        }
                    }

                    // Vehicle info is no longer collected/validated at Step 1 --
                    // it's prompted later in-flow (waiting/arrival state) once
                    // Booking::needsVehicleInfoPrompt() is true.
                    var parkingIsYes = parkingChecked2 ? parkingChecked2.value === "1" : {{ $booking->parking_needed ? 'true' : 'false' }};
                    var timeSelect = step.querySelector('#checkin_time_preference_select');
                    if (timeSelect && !timeSelect.value) {
                        timeSelect.classList.add("border-red-400");
                        var timeError = document.getElementById("checkin-time-error");
                        if (timeError) timeError.style.display = "block";
                        timeSelect.scrollIntoView({ behavior: "smooth", block: "center" });
                        timeSelect.focus();
                        return;
                    }
                    var checkoutSelect = step.querySelector('#checkout_time_preference_select');
                    if (checkoutSelect && !checkoutSelect.value) {
                        checkoutSelect.classList.add("border-red-400");
                        var checkoutError = document.getElementById("checkout-time-error");
                        if (checkoutError) checkoutError.style.display = "block";
                        checkoutSelect.scrollIntoView({ behavior: "smooth", block: "center" });
                        checkoutSelect.focus();
                        return;
                    }
                    var step1TermsCheckbox = document.getElementById("terms-accepted-checkbox");
                    if (step1TermsCheckbox && !step1TermsCheckbox.checked) {
                        alert("Please agree to the Terms of Service, Privacy Policy, and Rental Contract to continue.");
                        step1TermsCheckbox.scrollIntoView({ behavior: "smooth", block: "center" });
                        return;
                    }

                    var contractNameInput = document.getElementById("contract-signed-name");
                    if (contractNameInput && step1TermsCheckbox && step1TermsCheckbox.checked) {
                        var contractNameError = document.getElementById("contract-name-error");
                        var normalizeName = function (v) { return (v || "").replace(/\s+/g, " ").trim().toLowerCase(); };
                        if (normalizeName(contractNameInput.value) !== normalizeName(contractNameInput.dataset.legalName)) {
                            if (contractNameError) contractNameError.style.display = "block";
                            contractNameInput.classList.add("border-red-400");
                            contractNameInput.scrollIntoView({ behavior: "smooth", block: "center" });
                            contractNameInput.focus();
                            return;
                        }
                    }

                    var loginFd = new FormData();
                    loginFd.append("_token", document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : "");
                    if (step1TermsCheckbox) {
                        loginFd.append("terms_accepted", step1TermsCheckbox.checked ? "1" : "0");
                        loginFd.append("contract_accepted", step1TermsCheckbox.checked ? "1" : "0");
                    }
                    if (contractNameInput) loginFd.append("contract_signed_name", contractNameInput.value);
                    if (contractNameInput) {
                        var deviceId = "";
                        try {
                            var dk = "gh_device_id";
                            deviceId = window.localStorage.getItem(dk) || "";
                            if (!deviceId) {
                                deviceId = (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2));
                                window.localStorage.setItem(dk, deviceId);
                            }
                        } catch (e) {}
                        loginFd.append("contract_signed_device_id", deviceId);
                    }
                    ["guest_name", "phone", "email", "checkin_time_preference", "checkout_time_preference"].forEach(function(name) {
                        var input = step.querySelector('[name="' + name + '"]');
                        if (input) loginFd.append(name, input.value);
                    });
                    var step1PhoneCountryCode = document.getElementById("guest-phone-country-code");
                    if (step1PhoneCountryCode) loginFd.append("phone_country_code", step1PhoneCountryCode.value);
                    if (parkingChecked2) loginFd.append("parking_needed", parkingChecked2.value);
                    if (step1TermsCheckbox) {
                        loginFd.append("terms_accepted", step1TermsCheckbox.checked ? "1" : "0");
                    }
                    var step1SmsConsentCheckbox = document.getElementById("sms-consent-checkbox");
                    if (step1SmsConsentCheckbox) {
                        loginFd.append("sms_consent", step1SmsConsentCheckbox.checked ? "1" : "0");
                    }

                    withButtonBusy(btn, "Saving…", function(restore) {
                        fetch("{{ route('guest.login', [$booking->booking_id, $booking->token]) }}", {
                            method: "POST",
                            body: loginFd,
                            headers: { "Accept": "application/json" }
                        })
                            .then(function(r) {
                                if (r.status === 422) {
                                    return r.json().then(function(body) {
                                        restore();
                                        if (body.errors && body.errors.contract_signed_name && contractNameInput) {
                                            var contractNameError = document.getElementById("contract-name-error");
                                            if (contractNameError) contractNameError.style.display = "block";
                                            contractNameInput.classList.add("border-red-400");
                                            contractNameInput.scrollIntoView({ behavior: "smooth", block: "center" });
                                            contractNameInput.focus();
                                            return;
                                        }
                                        var messages = body.errors ? Object.values(body.errors).flat().join("\n") : "Please check the form and try again.";
                                        alert(messages);
                                    });
                                }
                                if (!r.ok) {
                                    restore();
                                    alert("Something went wrong. Please try again.");
                                    return;
                                }
                                restore();
                                goToStep(2);
                            })
                            .catch(function() {
                                restore();
                                alert("Network error. Please try again.");
                            });
                    });
                });

                var contractNameField = document.getElementById("contract-signed-name");
                if (contractNameField) {
                    contractNameField.addEventListener("input", function () {
                        var err = document.getElementById("contract-name-error");
                        if (err) err.style.display = "none";
                        contractNameField.classList.remove("border-red-400");
                    });
                }

                // ── Step 2 "Next": validate + AJAX-submit photos via submitIdentity, then advance to Step 3 ──
                document.getElementById("id-capture-next-btn").addEventListener("click", function() {
                    var btn = this;
                    if (photoIdRequired) {
                        var front = document.getElementById("photo-id-data").value;
                        var back = document.getElementById("photo-id-back-data").value;
                        var frontBlur = document.getElementById("front-blur-warning");
                        var backBlur = document.getElementById("back-blur-warning");
                        if (idwFrontRequired && !front) { alert("Please take a photo of the front of your ID."); return; }
                        if (!isPassport && idwBackRequired && !back) { alert("Please take a photo of the back of your ID."); return; }
                        if (idwFrontRequired && !frontBlur.classList.contains("hidden")) { alert("Front ID photo is blurry. Please retake."); return; }
                        if (!isPassport && idwBackRequired && !backBlur.classList.contains("hidden")) { alert("Back ID photo is blurry. Please retake."); return; }
                    }
                    function b64toBlob(b64) {
                        var arr = b64.split(","), mime = arr[0].match(/:(.*?);/)[1];
                        var bstr = atob(arr[1]), n = bstr.length, u8 = new Uint8Array(n);
                        for (var i = 0; i < n; i++) u8[i] = bstr.charCodeAt(i);
                        return new Blob([u8], {type: mime});
                    }
                    var form = document.getElementById("guest-booking-form");
                    var fd = new FormData();
                    fd.append("_token", document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : "");
                    if (photoIdRequired) {
                        if (idwFrontRequired) {
                            fd.set("photo_id", b64toBlob(document.getElementById("photo-id-data").value), "front.jpg");
                        }
                        if (!isPassport && idwBackRequired) {
                            fd.set("photo_id_back", b64toBlob(document.getElementById("photo-id-back-data").value), "back.jpg");
                        }
                    }

                    withButtonBusy(btn, "Uploading…", function(restore) {
                        fetch(form.action, {
                            method: "POST",
                            body: fd,
                            headers: { "Accept": "application/json" }
                        })
                            .then(function(r) {
                                if (r.status === 422) {
                                    return r.json().then(function(body) {
                                        restore();
                                        var messages = body.errors ? Object.values(body.errors).flat().join("\n") : "Please check the form and try again.";
                                        alert(messages);
                                    });
                                }
                                if (!r.ok && !r.redirected) {
                                    restore();
                                    return r.text().then(function(t) {
                                        console.error("Server error:", t);
                                        alert("Upload failed (server error). Please try again.");
                                    });
                                }
                                var parseFailed = false;
                                return r.json().catch(function() { parseFailed = true; return {}; }).then(function(body) {
                                    restore();

                                    // The server response wasn't valid JSON (most likely an
                                    // expired session/token redirect swallowed by fetch as a
                                    // followed 200). Treating this the same as "scan passed"
                                    // let guests silently fall through to signing/Step 3 with
                                    // no real verification having happened. Surface it instead.
                                    if (parseFailed) {
                                        alert("Something went wrong verifying your ID. Please refresh the page and try again.");
                                        return;
                                    }

                                    var idScan = body.id_scan || null;

                                    // Expired ID or a name that clearly doesn't match: reject
                                    // instantly, let the guest re-take/re-upload the photo.
                                    if (idScan && !idScan.passed) {
                                        idwClearState();
                                        resetIdCapture();
                                        alert(idScan.blocking_reason || "We couldn't verify your ID. Please upload a clear photo of a valid, unexpired ID.");
                                        return;
                                    }

                                    var signPanel = document.getElementById("agreement-sign-panel");
                                    if (signPanel && !signPanel.dataset.signed) {
                                        idwClearState();
                                        var signNameInput = document.getElementById("agreement-sign-name");
                                        if (signNameInput) signNameInput.dataset.legalName = (idScan && idScan.name) || "{{ $booking->guest_name }}";
                                        var idCaptureActions = document.getElementById("id-capture-actions");
                                        if (idCaptureActions) idCaptureActions.classList.add("hidden");
                                        signPanel.classList.remove("hidden");
                                        signPanel.scrollIntoView({ behavior: "smooth", block: "center" });
                                        return;
                                    }

                                    idwClearState();
                                    goToStep(3);

                                    var prompt = document.getElementById("completion-prompt");
                                    if (prompt) {
                                        prompt.classList.remove("hidden");
                                        localStorage.setItem(document.getElementById("guest-tour-data")?.dataset.tourKey || "guest_tour_seen", "1");
                                    }
                                });
                            })
                            .catch(function(e) {
                                restore();
                                console.error(e);
                                alert("Upload failed. Please try again.");
                            });
                    });
                });

                // ── Rental agreement signature (Step 2, after ID scan passes) ──
                var agreementSignBtn = document.getElementById("agreement-sign-btn");
                if (agreementSignBtn) {
                    agreementSignBtn.addEventListener("click", function() {
                        var btn = this;
                        var nameInput = document.getElementById("agreement-sign-name");
                        var errorEl = document.getElementById("agreement-sign-error");
                        var normalizeName = function (v) { return (v || "").replace(/\s+/g, " ").trim().toLowerCase(); };

                        if (errorEl) errorEl.style.display = "none";
                        if (nameInput) nameInput.classList.remove("border-red-400");

                        if (!nameInput || normalizeName(nameInput.value) !== normalizeName(nameInput.dataset.legalName)) {
                            if (errorEl) errorEl.style.display = "block";
                            if (nameInput) { nameInput.classList.add("border-red-400"); nameInput.focus(); }
                            return;
                        }

                        var deviceId = "";
                        try {
                            deviceId = window.localStorage.getItem("gh_device_id") || "";
                        } catch (e) {}

                        var fd = new FormData();
                        fd.append("_token", document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : "");
                        fd.append("contract_signed_name", nameInput.value);
                        fd.append("contract_signed_device_id", deviceId);

                        withButtonBusy(btn, "Signing…", function(restore) {
                            fetch("{{ route('guest.sign-rental-agreement', [$booking->booking_id, $booking->token]) }}", {
                                method: "POST",
                                body: fd,
                                headers: { "Accept": "application/json" }
                            })
                                .then(function(r) {
                                    if (r.status === 422) {
                                        return r.json().then(function(body) {
                                            restore();
                                            if (errorEl) errorEl.style.display = "block";
                                            nameInput.classList.add("border-red-400");
                                            var messages = body.errors ? Object.values(body.errors).flat().join("\n") : "Please check your name and try again.";
                                            alert(messages);
                                        });
                                    }
                                    if (!r.ok) {
                                        restore();
                                        alert("Something went wrong. Please try again.");
                                        return;
                                    }
                                    restore();
                                    var signPanel = document.getElementById("agreement-sign-panel");
                                    if (signPanel) signPanel.dataset.signed = "1";
                                    goToStep(3);
                                    var prompt = document.getElementById("completion-prompt");
                                    if (prompt) {
                                        prompt.classList.remove("hidden");
                                        localStorage.setItem(document.getElementById("guest-tour-data")?.dataset.tourKey || "guest_tour_seen", "1");
                                    }
                                })
                                .catch(function() {
                                    restore();
                                    alert("Network error. Please try again.");
                                });
                        });
                    });
                }

                // ── Step 3 "Continue": no network call — data already saved, just reload to reflect pending-approval state ──
                document.getElementById("smart-lock-continue-btn").addEventListener("click", function() {
                    idwClearState();
                    window.location.reload();
                });
                </script>
            </div>
        @elseif($state === 'waiting')
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-ready">
                        <x-icon name="calendar" class="h-4 w-4" />
                        Not checked in
                    </span>
                </div>
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full mt-4">
                <div class="px-6 pt-8 pb-2 text-center">
                    <div class="guest-big-check">
                        <x-icon name="check" class="h-8 w-8" />
                    </div>
                    <h2 class="mt-4 text-xl font-extrabold text-slate-950">Approved for check in!</h2>
                </div>
                <div class="px-6 pb-6">
                    <div class="guest-stay-grid">
                        <div class="guest-stay-tile">
                            <div class="guest-stay-tile-icon">
                                <x-icon name="calendar" class="h-5 w-5" />
                            </div>
                            <p class="guest-stay-tile-label">Check-In</p>
                            <p class="guest-stay-tile-date">{{ $booking->check_in_date->format('M d, Y') }}</p>
                            <p class="guest-stay-tile-time">{{ $booking->effectiveCheckinTimeFormatted() }}</p>
                        </div>
                        <div class="guest-stay-tile">
                            <div class="guest-stay-tile-icon">
                                <x-icon name="calendar" class="h-5 w-5" />
                            </div>
                            <p class="guest-stay-tile-label">Check-Out</p>
                            <p class="guest-stay-tile-date">{{ $booking->check_out_date->format('M d, Y') }}</p>
                            <p class="guest-stay-tile-time">{{ $booking->effectiveCheckoutTimeFormatted() }}</p>
                        </div>
                    </div>

                    <div class="guest-detail-banner">
                        <span class="guest-detail-banner-icon">
                            <x-icon name="check" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="guest-detail-banner-title">Check In Details Available</p>
                            <p class="guest-detail-banner-sub">{{ $booking->check_in_date->format('M d, Y') }} at {{ $booking->addressAvailableAtFormatted() }}</p>
                            <p class="guest-detail-banner-sub mt-1">Please come back then for property address and check in details.</p>
                        </div>
                    </div>

                    <button class="guest-primary-btn mt-5 w-full" disabled>Check In</button>
                </div>
            </div>
        @elseif($state === 'vehicle_info')
            <div class="guest-portal-card">
                <div data-poll-id-status="{{ route('guest.id-status', [$booking->booking_id, $booking->token]) }}" data-poll-fields="vehicle_info_bypassed" data-id-state-key="idw_form_state_{{ $booking->booking_id }}" data-id-rejection-key="idw_id_rejection_seen_{{ $booking->booking_id }}"></div>
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-ready">
                        <x-icon name="calendar" class="h-4 w-4" />
                        Almost there
                    </span>
                </div>
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full mt-4">
                <div class="px-6 pt-8 pb-2 text-center">
                    <div class="guest-big-check">
                        <x-icon name="car" class="h-8 w-8" />
                    </div>
                    <h2 class="mt-4 text-xl font-extrabold text-slate-950">Vehicle Information</h2>
                    <p class="mt-2 text-sm text-slate-600">Please upload a clear photo of your vehicle's license plate before check-in.</p>
                </div>
                <div class="px-6 pb-6">
                    <form method="post" enctype="multipart/form-data" action="{{ route('guest.vehicle-info', [$booking->booking_id, $booking->token]) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="license_plate_photo" class="guest-stay-tile-label block mb-1">License Plate Photo</label>
                            <input type="file" name="license_plate_photo" id="license_plate_photo"
                                   accept="image/*" class="guest-input w-full" required>
                            @error('license_plate_photo')
                                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="guest-primary-btn mt-2 w-full">Submit</button>
                    </form>
                    <div class="guest-detail-banner mt-4">
                        <span class="guest-detail-banner-icon">
                            <x-icon name="phone" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="guest-detail-banner-title">Can't provide this right now?</p>
                            <p class="guest-detail-banner-sub">Use the Call Guest Services button and we'll take care of it for you.</p>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($state === 'arrival')
            @php
                $arrivalAgreed = $booking->hasAgreedToArrivalDisclaimer();
                $arrivalDisclaimer = \App\Models\Setting::getValue(
                    'arrival_disclaimer',
                    "<p><strong>Before you start navigating to the property, please read your arrival instructions.</strong> Guests who arrive without reading them often end up waiting outside and messaging us for entry. Please go through every step first, then type <strong>agree</strong> below to continue.</p>"
                );
            @endphp
            @if($arrivalAgreed)
                <div data-poll-gps-status="{{ route('guest.gps-status', [$booking->booking_id, $booking->token]) }}"></div>
            @endif
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill">
                        <x-icon name="alert-triangle" class="h-4 w-4" />
                        Not checked in
                    </span>
                </div>
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full rounded-xl mt-4">
            <div class="p-6 md:p-10">
                <h1 class="guest-status-title">We Can't Wait To See You!</h1>
                <div class="guest-stay-grid">
                    <div class="guest-stay-tile">
                        <div class="guest-stay-tile-icon">
                            <x-icon name="calendar" class="h-5 w-5" />
                        </div>
                        <p class="guest-stay-tile-label">Check-In</p>
                        <p class="guest-stay-tile-date">{{ $booking->check_in_date->format('M d, Y') }}</p>
                        <p class="guest-stay-tile-time">{{ $booking->effectiveCheckinTimeFormatted() }}</p>
                    </div>
                    <div class="guest-stay-tile">
                        <div class="guest-stay-tile-icon">
                            <x-icon name="calendar" class="h-5 w-5" />
                        </div>
                        <p class="guest-stay-tile-label">Check-Out</p>
                        <p class="guest-stay-tile-date">{{ $booking->check_out_date->format('M d, Y') }}</p>
                        <p class="guest-stay-tile-time">{{ $booking->effectiveCheckoutTimeFormatted() }}</p>
                    </div>
                </div>

                @if(! $arrivalAgreed)
                    <div class="mt-5 rounded-xl border-2 border-amber-300 bg-amber-50 p-5 text-left">
                        <div class="flex items-center gap-2 text-amber-900">
                            <x-icon name="alert-triangle" class="h-5 w-5 shrink-0" />
                            <p class="font-bold">Please read this before you travel</p>
                        </div>
                        <div class="mt-3 text-sm leading-6 text-amber-900">{!! $arrivalDisclaimer !!}</div>
                        <div class="mt-5">
                            <label class="block text-sm font-semibold text-amber-900" for="arrival-agree-input">Type <span class="font-mono font-bold">agree</span> to continue</label>
                            <input id="arrival-agree-input" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" disabled placeholder="agree" class="guest-input mt-2">
                        </div>
                        <form id="arrival-agree-form" method="post" action="{{ route('guest.arrival-agree', [$booking->booking_id, $booking->token]) }}" class="mt-4">
                            @csrf
                            <button type="submit" id="arrival-agree-btn" class="guest-primary-btn w-full" disabled>Continue (<span id="arrival-agree-countdown">20</span>s)</button>
                        </form>
                    </div>
                @elseif($booking->canViewAddress())
                    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-sm">
                        <p class="font-semibold text-slate-800">Property Address</p>
                        <p class="mt-1 text-slate-600">{{ $property->shortAddress() }}</p>
                        @php
                            $navigationUrl = $property->latitude && $property->longitude
                                ? 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($property->latitude.','.$property->longitude)
                                : $property->map_directions_url;
                        @endphp
                        @if($navigationUrl)
                            <a href="{{ $navigationUrl }}" target="_blank" rel="noopener" class="guest-outline-btn mt-3 inline-flex w-full items-center justify-center gap-2">
                                <img src="{{ asset('img/google-maps-icon.png') }}" alt="" class="h-6 w-6">
                                Navigate with Google Maps
                            </a>
                        @endif
                    </div>
                @else
                    <div class="guest-detail-banner">
                        <span class="guest-detail-banner-icon">
                            <x-icon name="check" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="guest-detail-banner-title">Check In Details Available</p>
                            <p class="guest-detail-banner-sub">{{ $booking->check_in_date->format('M d, Y') }} at {{ $booking->addressAvailableAtFormatted() }}</p>
                            <p class="guest-detail-banner-sub mt-1">Please come back then for property address and check in details.</p>
                        </div>
                    </div>
                @endif
            </div>
            </div>

            @if($arrivalAgreed && $booking->canViewAddress())
            <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-center">
                <p class="text-base font-bold text-slate-950">{!! $gpsVerifyMessage !!}</p>
                <div id="gps-ajax-message" class="hidden"></div>
                <button id="gps-ajax-verify-btn" type="button" data-url="{{ route('guest.gps', [$booking->booking_id, $booking->token]) }}" data-csrf="{{ csrf_token() }}" class="guest-primary-btn is-go mt-4 w-full">I Have Arrived</button>
                <p class="mt-3 text-xs leading-5 text-slate-500">Please make sure your location is allowed. We will verify on the next page.</p>
            </div>
            @endif

            @if(! $arrivalAgreed)
            <script>
            (function () {
                var input = document.getElementById('arrival-agree-input');
                var btn = document.getElementById('arrival-agree-btn');
                var countdownEl = document.getElementById('arrival-agree-countdown');
                var form = document.getElementById('arrival-agree-form');
                if (!input || !btn || !form) return;

                var seconds = 20;
                var ready = false;

                function refresh() {
                    var typed = (input.value || '').trim().toLowerCase() === 'agree';
                    btn.disabled = !(ready && typed);
                }

                var timer = window.setInterval(function () {
                    seconds -= 1;
                    if (seconds <= 0) {
                        window.clearInterval(timer);
                        ready = true;
                        if (countdownEl) countdownEl.textContent = '0';
                        input.disabled = false;
                        input.focus();
                        btn.textContent = 'Continue';
                        refresh();
                        return;
                    }
                    if (countdownEl) countdownEl.textContent = String(seconds);
                }, 1000);

                input.addEventListener('input', refresh);
            })();
            </script>
            @endif
        @elseif($state === 'awaiting_deposit')
            <div data-poll-id-status="{{ route('guest.id-status', [$booking->booking_id, $booking->token]) }}" data-poll-fields="deposit_verified"></div>
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill{{ $booking->isDepositCaptured() ? ' is-ready' : '' }}">
                        @if($booking->platformPaymentSelected() && ! $booking->isDepositCaptured())
                            <x-icon name="clock" class="h-4 w-4" />
                            Pending approval
                        @else
                            <x-icon name="{{ $booking->isDepositCaptured() ? 'check' : 'clock' }}" class="h-4 w-4" />
                            {{ $booking->isDepositCaptured() ? 'Deposit paid' : 'Awaiting deposit' }}
                        @endif
                    </span>
                </div>
                <div class="px-6 pt-5">
                    <h1 class="guest-status-title">Almost there</h1>
                </div>
                <img src="{{ $heroImg }}" alt="{{ $property->name }}" class="guest-hero-img w-full rounded-xl mt-4">
                <div class="p-6 md:p-10">
                    @php
                        $depositAmountCents = $booking->calculatePreCheckinChargeCents();
                        $stripeConfigured = filled(config('services.stripe.key')) && filled(config('services.stripe.secret'));
                        $platformLabel = $booking->platformLabel();
                        $otaInstructions = str_replace(
                            '[[platform]]',
                            $platformLabel,
                            \App\Models\Setting::getValue(
                                'airbnb_payment_instructions',
                                "<p>We'll send you a payment request through [[platform]]. Once it's completed, we'll confirm and send your check-in details.</p>"
                            )
                        );
                    @endphp
                    @if($booking->isDepositCaptured())
                        <div class="text-center">
                            <div class="guest-big-check mx-auto">
                                <x-icon name="check" class="h-8 w-8" />
                            </div>
                            <h2 class="mt-4 text-xl font-extrabold text-slate-950">Payment received</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">Thank you. Your payment has been received. We are confirming your deposit now, and you will receive a message with your check-in details once it has been verified.</p>
                        </div>
                    @elseif($booking->platformPaymentSelected())
                        <div class="text-center">
                            <div class="guest-big-check mx-auto" style="background:#fef3c7;color:#b45309;">
                                <x-icon name="clock" class="h-8 w-8" />
                            </div>
                            <h2 class="mt-4 text-xl font-extrabold text-slate-950">Pending approval</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">We've received your registration. Your incidentals hold is being handled through {{ $platformLabel }}, so there's nothing more you need to do right now. We'll message you as soon as your reservation is approved and the next step is ready.</p>
                        </div>
                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left text-sm leading-6 text-slate-600">
                            {!! $otaInstructions !!}
                        </div>
                    @elseif($stripeConfigured && $depositAmountCents > 0)
                        <div class="text-center">
                            <h2 class="text-xl font-extrabold text-slate-950">Incidentals payment</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600">A payment of <strong>${{ number_format($depositAmountCents / 100, 2) }}</strong> is required before check-in.</p>
                        </div>

                        <div id="deposit-payment-choice" class="mt-5">
                            <button type="button" id="deposit-pay-here-btn" class="guest-primary-btn w-full">Pay Here with Card</button>
                            <button type="button" id="deposit-pay-airbnb-btn" class="guest-outline-btn w-full mt-3 js-select-platform" data-platform-url="{{ route('guest.deposit.platform', [$booking->booking_id, $booking->token]) }}">Pay on {{ $platformLabel }}</button>
                        </div>

                        <div id="deposit-card-section" class="hidden">
                        <div id="deposit-payment-error" class="mt-4 hidden rounded-lg bg-red-50 p-3 text-center text-sm text-red-700"></div>
                        <form id="deposit-payment-form" class="mt-5" data-intent-url="{{ route('guest.deposit.intent', [$booking->booking_id, $booking->token]) }}" data-confirm-url="{{ route('guest.deposit.confirm', [$booking->booking_id, $booking->token]) }}">
                            <div class="guest-card-wizard" id="deposit-card-wizard">
                                <div class="guest-card-summary-list" id="deposit-summary-list"></div>
                                <div class="guest-card-step" data-step="number" id="deposit-step-number">
                                    <label class="guest-card-label" for="deposit-payment-card-number">Card number</label>
                                    <div id="deposit-payment-card-number" class="guest-card-field"></div>
                                </div>
                                <div class="guest-card-step hidden" data-step="expiry" id="deposit-step-expiry">
                                    <label class="guest-card-label" for="deposit-payment-card-expiry">Expiry date</label>
                                    <div id="deposit-payment-card-expiry" class="guest-card-field"></div>
                                </div>
                                <div class="guest-card-step hidden" data-step="cvc" id="deposit-step-cvc">
                                    <label class="guest-card-label" for="deposit-payment-card-cvc">Security code (CVC)</label>
                                    <div id="deposit-payment-card-cvc" class="guest-card-field"></div>
                                </div>
                                <div class="guest-card-step hidden" data-step="postal" id="deposit-step-postal">
                                    <label class="guest-card-label" for="deposit-payment-card-postal">Billing ZIP / postal code</label>
                                    <input id="deposit-payment-card-postal" name="postal-code" type="text" autocomplete="postal-code" maxlength="12" placeholder="ZIP / Postal code" class="guest-card-input" aria-label="Billing ZIP / postal code">
                                </div>
                            </div>
                            <button type="submit" id="deposit-pay-btn" class="guest-primary-btn mt-4 w-full" disabled>Pay ${{ number_format($depositAmountCents / 100, 2) }}</button>
                        </form>
                        </div>
                        <script src="https://js.stripe.com/v3/"></script>
                        <script>
                        (function() {
                            var choiceBlock = document.getElementById("deposit-payment-choice");
                            var cardSection = document.getElementById("deposit-card-section");
                            var payHereBtn = document.getElementById("deposit-pay-here-btn");
                            var stripeInitStarted = false;

                            var form = document.getElementById("deposit-payment-form");
                            var payBtn = document.getElementById("deposit-pay-btn");
                            var errorBox = document.getElementById("deposit-payment-error");
                            var stripe, elements, cardNumber, cardExpiry, cardCvc, clientSecret = null;
                            var postalField = document.getElementById("deposit-payment-card-postal");
                            var summaryList = document.getElementById("deposit-summary-list");
                            var stepNumber = document.getElementById("deposit-step-number");
                            var stepExpiry = document.getElementById("deposit-step-expiry");
                            var stepCvc = document.getElementById("deposit-step-cvc");
                            var stepPostal = document.getElementById("deposit-step-postal");

                            function showError(msg) {
                                errorBox.textContent = msg;
                                errorBox.classList.remove("hidden");
                            }

                            function clearError() {
                                errorBox.classList.add("hidden");
                            }

                            function showSuccess(msg) {
                                var container = form.closest(".p-6, .p-10, [class*='p-6']") || form.parentElement;
                                container.innerHTML = "";
                                var check = document.createElement("div");
                                check.className = "guest-big-check mx-auto";
                                check.textContent = "✓";
                                var heading = document.createElement("h2");
                                heading.className = "mt-4 text-xl font-extrabold text-slate-950";
                                heading.textContent = "Payment received";
                                var message = document.createElement("p");
                                message.className = "mt-3 text-sm leading-6 text-slate-600";
                                message.textContent = (msg || "Your payment has been received.") + " We are confirming your deposit now, and you will receive a message with your check-in details once it has been verified.";
                                var success = document.createElement("div");
                                success.className = "text-center";
                                success.append(check, heading, message);
                                container.appendChild(success);
                            }

                            function addSummaryRow(label) {
                                var row = document.createElement("div");
                                row.className = "guest-card-summary-item";
                                var icon = document.createElement("span");
                                icon.textContent = "✓";
                                var text = document.createElement("span");
                                text.textContent = label;
                                row.append(icon, text);
                                summaryList.appendChild(row);
                            }

                            function hideStep(step) {
                                step.classList.add("hidden");
                            }

                            function showStep(step) {
                                step.classList.remove("hidden");
                            }

                            function brandLabel(brand) {
                                var map = { visa: "Visa", mastercard: "Mastercard", amex: "Amex", discover: "Discover", diners: "Diners Club", jcb: "JCB", unionpay: "UnionPay" };
                                if (map[brand]) return map[brand];
                                return brand ? brand.charAt(0).toUpperCase() + brand.slice(1) : "Card";
                            }

                            function makeStripeFieldAccessible(fieldId, label, autocomplete) {
                                var attempts = 0;
                                var timer = window.setInterval(function() {
                                    var field = document.querySelector('#' + fieldId + ' .__PrivateStripeElement-input');
                                    if (field) {
                                        field.setAttribute('aria-hidden', 'false');
                                        field.setAttribute('aria-label', label);
                                        field.setAttribute('autocomplete', autocomplete);
                                        window.clearInterval(timer);
                                        return;
                                    }
                                    attempts += 1;
                                    if (attempts >= 25) {
                                        window.clearInterval(timer);
                                    }
                                }, 100);
                            }

                            postalField.addEventListener("input", function() {
                                clearError();
                                if (postalField.value.trim().length >= 3) {
                                    payBtn.disabled = false;
                                    payBtn.classList.add("is-go");
                                } else {
                                    payBtn.disabled = true;
                                    payBtn.classList.remove("is-go");
                                }
                            });

                            function initStripeCardForm() {
                                if (stripeInitStarted || !form) return;
                                stripeInitStarted = true;

                                fetch(form.dataset.intentUrl, {
                                    method: "POST",
                                    headers: {
                                        "Accept": "application/json",
                                        "Content-Type": "application/json",
                                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : ""
                                    }
                                })
                                    .then(function(r) { return r.json(); })
                                    .then(function(data) {
                                        if (!data.ok) {
                                            showError(data.error || "Unable to start payment. Please contact us.");
                                            return;
                                        }
                                        clientSecret = data.client_secret;
                                        stripe = Stripe(data.publishable_key);
                                        elements = stripe.elements({ clientSecret: clientSecret });

                                        var stripeElementStyle = {
                                            base: {
                                                color: "#0f172a",
                                                fontSize: "16px",
                                                fontFamily: "inherit",
                                                lineHeight: "24px",
                                "::placeholder": { color: "#94a3b8" }
                                            },
                                            invalid: { color: "#dc2626" }
                                        };
                                        cardNumber = elements.create("cardNumber", {
                                            showIcon: true,
                                            placeholder: "1234 5678 9012 3456",
                                            classes: { base: "guest-card-field-inner" },
                                            style: stripeElementStyle
                                        });
                                        cardExpiry = elements.create("cardExpiry", {
                                            placeholder: "MM / YY",
                                            classes: { base: "guest-card-field-inner" },
                                            style: stripeElementStyle
                                        });
                                        cardCvc = elements.create("cardCvc", {
                                            placeholder: "CVV",
                                            classes: { base: "guest-card-field-inner" },
                                            style: stripeElementStyle
                                        });
                                        cardNumber.mount("#deposit-payment-card-number");
                                        cardExpiry.mount("#deposit-payment-card-expiry");
                                        cardCvc.mount("#deposit-payment-card-cvc");
                                        makeStripeFieldAccessible("deposit-payment-card-number", "Card number", "cc-number");
                                        makeStripeFieldAccessible("deposit-payment-card-expiry", "Card expiry", "cc-exp");
                                        makeStripeFieldAccessible("deposit-payment-card-cvc", "Card security code", "cc-csc");

                                        var numberDone = false, expiryDone = false, cvcDone = false;
                                        var cardBrand = "Card";

                                        cardNumber.on("change", function(event) {
                                            if (event.error) { showError(event.error.message); return; }
                                            errorBox.classList.add("hidden");
                                            if (event.brand && event.brand !== "unknown") cardBrand = brandLabel(event.brand);
                                            if (event.complete && !numberDone) {
                                                numberDone = true;
                                                hideStep(stepNumber);
                                                addSummaryRow(cardBrand + " •••• •••• •••• ••••");
                                                showStep(stepExpiry);
                                                cardExpiry.focus();
                                            }
                                        });
                                        cardExpiry.on("change", function(event) {
                                            if (event.error) { showError(event.error.message); return; }
                                            errorBox.classList.add("hidden");
                                            if (event.complete && !expiryDone) {
                                                expiryDone = true;
                                                hideStep(stepExpiry);
                                                addSummaryRow("Expiry date entered");
                                                showStep(stepCvc);
                                                cardCvc.focus();
                                            }
                                        });
                                        cardCvc.on("change", function(event) {
                                            if (event.error) { showError(event.error.message); return; }
                                            errorBox.classList.add("hidden");
                                            if (event.complete && !cvcDone) {
                                                cvcDone = true;
                                                hideStep(stepCvc);
                                                addSummaryRow("Security code entered");
                                                showStep(stepPostal);
                                                postalField.focus();
                                            }
                                        });
                                    })
                                    .catch(function() { showError("Network error. Please try again."); });
                            }

                            if (payHereBtn) {
                                payHereBtn.addEventListener("click", function() {
                                    choiceBlock.classList.add("hidden");
                                    cardSection.classList.remove("hidden");
                                    initStripeCardForm();
                                });
                            }

                            if (form) {
                                form.addEventListener("submit", function(e) {
                                    e.preventDefault();
                                    if (!stripe || !elements || !clientSecret || !cardNumber) return;
                                    payBtn.disabled = true;
                                    payBtn.textContent = "Processing…";
                                    errorBox.classList.add("hidden");

                                    stripe.confirmCardPayment(clientSecret, {
                                        payment_method: {
                                            card: cardNumber,
                                            billing_details: {
                                                address: {
                                                    postal_code: postalField.value.trim()
                                                }
                                            }
                                        }
                                    })
                                        .then(function(result) {
                                            if (result.error) {
                                                showError(result.error.message || "Payment failed. Please try again.");
                                                payBtn.disabled = false;
                                                payBtn.textContent = "Pay {{ '$' . number_format($depositAmountCents / 100, 2) }}";
                                                return;
                                            }
                                            return fetch(form.dataset.confirmUrl, {
                                                method: "POST",
                                                headers: {
                                                    "Accept": "application/json",
                                                    "Content-Type": "application/json",
                                                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : ""
                                                },
                                                body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
                                            })
                                                .then(function(r) { return r.json(); })
                                                .then(function(confirmData) {
                                                    if (confirmData.ok) {
                                                        showSuccess(confirmData.message);
                                                        // Deposit is auto-verified server-side now,
                                                        // so reload to advance past this screen.
                                                        setTimeout(function() { window.location.reload(); }, 1200);
                                                    } else {
                                                        showError(confirmData.error || "Payment could not be confirmed. Please contact us.");
                                                        payBtn.disabled = false;
                                                        payBtn.textContent = "Pay {{ '$' . number_format($depositAmountCents / 100, 2) }}";
                                                    }
                                                });
                                        })
                                        .catch(function() {
                                            showError("Network error confirming payment. Please try again.");
                                            payBtn.disabled = false;
                                            payBtn.textContent = "Pay {{ '$' . number_format($depositAmountCents / 100, 2) }}";
                                        });
                                });
                            }
                        })();
                        </script>
                    @elseif($booking->status === 'pre_checkin_complete')
                        <div class="text-center">
                        <h2 class="text-xl font-extrabold text-slate-950">Pre-check in completed!</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Please submit your required incidentals hold payment on the booking platform. This hold is refundable after check out.</p>
                        </div>
                        <button type="button" class="guest-primary-btn mt-5 w-full js-select-platform" data-platform-url="{{ route('guest.deposit.platform', [$booking->booking_id, $booking->token]) }}">I'll pay on {{ $platformLabel }}</button>
                    @else
                        <div class="text-center">
                        <h2 class="text-xl font-extrabold text-slate-950">Pending incidentals hold payment</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">If you have already submitted the payment, please send us a message so that we can expedite this for you. It usually doesn't take that long.</p>
                        </div>
                        <button type="button" class="guest-primary-btn mt-5 w-full js-select-platform" data-platform-url="{{ route('guest.deposit.platform', [$booking->booking_id, $booking->token]) }}">I'll pay on {{ $platformLabel }}</button>
                    @endif
                    <script>
                    (function () {
                        // "I'll pay on <platform>" — records the off-platform
                        // choice, then reloads into the pending-approval screen.
                        document.querySelectorAll(".js-select-platform").forEach(function (btn) {
                            btn.addEventListener("click", function () {
                                if (btn.disabled) return;
                                btn.disabled = true;
                                btn.textContent = "Saving…";
                                fetch(btn.dataset.platformUrl, {
                                    method: "POST",
                                    headers: {
                                        "Accept": "application/json",
                                        "Content-Type": "application/json",
                                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : ""
                                    }
                                }).then(function () { window.location.reload(); })
                                  .catch(function () { window.location.reload(); });
                            });
                        });
                    })();
                    </script>
                </div>
            </div>
        @elseif($state === 'checkout_notice')
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-checked">
                        <x-icon name="check" class="h-4 w-4" />
                        Checked in
                    </span>
                </div>
                <div class="p-6">
                    <h1 class="guest-status-title">Check-out is coming up</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Your check-out time is {{ $booking->effectiveCheckoutTimeFormatted() }} tomorrow. You'll still have full access to the guide until then.</p>
                    <a href="#guide-grid" class="guest-primary-btn w-full mt-4">View Guide</a>
                </div>
                {{-- Late checkout is deducted from the incidentals hold at
                     checkout, not billed to the guest separately here —
                     billing it again on top of the hold double-charges
                     the guest. See admin-side ledger for the actual
                     charge amount. --}}
                @if($locks->isNotEmpty())
                    <div class="px-6 pb-2">
                        <div class="grid gap-6 {{ $locks->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                            @foreach($locks as $entry)
                                <x-lock-card
                                    :booking-id="$booking->booking_id"
                                    :token="$booking->token"
                                    :lock-id="$entry['lock']->id"
                                    :lock-label="$locks->count() > 1 ? $entry['lock']->label : null"
                                    :lock-status="$entry['status']"
                                    :auto-checkin="! $booking->isMarkedCheckedIn()"
                                    :auto-checkout="$booking->isCheckoutDay()"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif
                <div id="guide-grid" class="guest-guide-grid p-6 pt-0">
                    @foreach($guideCats as $category)
                        @php
                            $colors = $categoryColor;
                            $displayTitle = $category->pivot->custom_title ?: $category->title;
                            $displayDescription = $category->pivot->custom_description ?: $category->description;
                        @endphp
                        <x-guide-panel
                            :href="route('guest.category', [$booking->booking_id, $booking->token, $category])"
                            :icon="$category->slug"
                            :guest-icon="$category->guest_icon"
                            :title="$displayTitle"
                            :description="$displayDescription"
                            :tone="$colors[0]"
                            :accent="$colors[1]"
                            :wide="$category->slug === 'checkout-instructions'"
                        />
                    @endforeach
                </div>
            </div>
        @elseif($state === 'checkout_available')
            <div data-poll-id-status="{{ route('guest.id-status', [$booking->booking_id, $booking->token]) }}" data-poll-fields="checked_out" class="hidden"></div>
            @if(count($checkoutSteps) > 0)
                <div id="checkout-wizard-wrapper" style="display:none">
                    <x-step-wizard :steps="$checkoutSteps" type="checkout" next-section="checkout-guide-section" :booking-id="$booking->booking_id" :token="$booking->token" :show-back-link="true" />
                </div>
            @endif
            <div class="guest-portal-card" id="checkout-guide-section">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-checked">
                        <x-icon name="check" class="h-4 w-4" />
                        Checked in
                    </span>
                </div>
                {{-- Late checkout is deducted from the incidentals hold at
                     checkout, not billed to the guest separately here —
                     billing it again on top of the hold double-charges
                     the guest. See admin-side ledger for the actual
                     charge amount. --}}
                <x-checkout-today-card :booking="$booking" :has-steps="count($checkoutSteps) > 0" class="p-6" />
                <div class="guest-guide-body px-6 pb-6">
                    <x-weather-badge :property="$property" class="guest-weather-card" />
                </div>
                @if($locks->isNotEmpty())
                    <div class="px-6 pb-2">
                        <div class="grid gap-6 {{ $locks->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                            @foreach($locks as $entry)
                                <x-lock-card
                                    :booking-id="$booking->booking_id"
                                    :token="$booking->token"
                                    :lock-id="$entry['lock']->id"
                                    :lock-label="$locks->count() > 1 ? $entry['lock']->label : null"
                                    :lock-status="$entry['status']"
                                    :auto-checkin="! $booking->isMarkedCheckedIn()"
                                    :auto-checkout="$booking->isCheckoutDay()"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif
                <div id="guide-grid" class="guest-guide-grid p-6 pt-0">
                    @foreach($guideCats as $category)
                        @php
                            $colors = $categoryColor;
                            $displayTitle = $category->pivot->custom_title ?: $category->title;
                            $displayDescription = $category->pivot->custom_description ?: $category->description;
                        @endphp
                        <x-guide-panel
                            :href="route('guest.category', [$booking->booking_id, $booking->token, $category])"
                            :icon="$category->slug"
                            :guest-icon="$category->guest_icon"
                            :title="$displayTitle"
                            :description="$displayDescription"
                            :tone="$colors[0]"
                            :accent="$colors[1]"
                            :wide="$category->slug === 'checkout-instructions'"
                        />
                    @endforeach
                </div>
            </div>
        @elseif($state === 'post_checkout')
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-checked">
                        <x-icon name="check" class="h-4 w-4" />
                        Checked out
                    </span>
                </div>
                <div class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center md:py-24">
                    <h1 class="guest-status-title">Thank you for staying with us!</h1>
                    <p class="max-w-md text-sm leading-6 text-slate-600">We appreciate it. If you'd like to stay with us again, please contact us directly for a discount.</p>
                </div>
            </div>
        @elseif($state === 'checkout_locked')
            @if($booking->status === 'checked_out')
                <div class="guest-portal-card">
                    <div class="guest-status-bar">
                        <div>
                            @if($siteLogo)
                                <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                            @endif
                        </div>
                        <span class="guest-status-pill is-checked">
                            <x-icon name="check" class="h-4 w-4" />
                            Checked out
                        </span>
                    </div>
                    <div class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center md:py-24">
                        <h1 class="guest-status-title">You're all checked out</h1>
                        <p class="max-w-md text-sm leading-6 text-slate-600">We appreciate it. If you'd like to stay with us again, please contact us directly for a discount.</p>
                    </div>
                </div>
            @elseif(count($checkoutSteps) > 0)
                <x-step-wizard :steps="$checkoutSteps" type="checkout" next-section="checkout-complete" :booking-id="$booking->booking_id" :token="$booking->token" />
                <div class="guest-portal-card" id="checkout-complete" style="display:none">
                    <div class="guest-status-bar">
                        <div>
                            @if($siteLogo)
                                <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                            @endif
                        </div>
                        <span class="guest-status-pill is-checked">
                            <x-icon name="check" class="h-4 w-4" />
                            Checked out
                        </span>
                    </div>
                    <div class="flex flex-col items-center justify-center gap-4 px-6 py-16 text-center md:py-24">
                        <h1 class="guest-status-title">You're all checked out</h1>
                        <p class="max-w-md text-sm leading-6 text-slate-600">We appreciate it. If you'd like to stay with us again, please contact us directly for a discount.</p>
                    </div>
                </div>
            @else
            <div class="guest-guide-open">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-checked">
                        <x-icon name="check" class="h-4 w-4" />
                        Checked in
                    </span>
                </div>
            <div class="guest-guide-body">
                <div class="px-6 pt-6">
                    <h1 class="guest-status-title">Check-out instructions</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Thank you for staying with us. Please review these steps before you leave.</p>
                </div>
                <img src="https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?auto=format&fit=crop&w=900&q=80" alt="Packed luggage in a clean room" class="h-48 w-full rounded-md object-cover md:h-72" loading="lazy">
                <ul class="mt-6 grid gap-4 text-sm">
                    @foreach([
                        'Check-out Time '.$booking->effectiveCheckoutTimeFormatted(),
                        'Ensure all belongings are collected.',
                        'Turn off lights and AC.',
                        'Leave the keys on the table.',
                        'Thank you for staying with us!',
                    ] as $item)
                        <li class="flex items-start gap-3">
                            <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
                @if($guideCats->count())
                    <a href="#guide-grid" class="guest-primary-btn mt-8 w-full">View Other Info</a>
                    <div id="guide-grid" class="guest-guide-grid mt-8">
                        @foreach($guideCats as $category)
                            @php
                                $colors = $categoryColor;
                                $displayTitle = $category->pivot->custom_title ?: $category->title;
                                $displayDescription = $category->pivot->custom_description ?: $category->description;
                            @endphp

                            <x-guide-panel
                                :href="route('guest.category', [$booking->booking_id, $booking->token, $category])"
                                :icon="$category->slug"
                                :guest-icon="$category->guest_icon"
                                :title="$displayTitle"
                                :description="$displayDescription"
                                :tone="$colors[0]"
                                :accent="$colors[1]"
                                :wide="$category->slug === 'checkout-instructions'"
                            />
                        @endforeach
                    </div>
                @endif
            </div>
            </div>
            @endif
        @else
            @if(count($parkingSteps) > 0)
                <x-step-wizard :steps="$parkingSteps" type="parking" :next-section="count($checkinSteps) > 0 ? 'step-wizard-checkin' : 'guest-guide-section'" />
            @endif
            @if(count($checkinSteps) > 0)
                <div id="step-wizard-checkin-wrapper" style="{{ count($parkingSteps) > 0 ? 'display:none' : '' }}">
                    <x-step-wizard :steps="$checkinSteps" type="checkin" next-section="guest-guide-section" :booking-id="$booking->booking_id" :token="$booking->token" />
                </div>
            @endif
            <div id="guest-guide-section" {{ (count($checkinSteps) > 0 || count($parkingSteps) > 0) && $booking->status !== 'checked_in' ? 'style=display:none' : '' }}>
            <div class="guest-portal-card">
                <div class="guest-status-bar">
                    <div>
                        @if($siteLogo)
                            <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
                        @endif
                    </div>
                    <span class="guest-status-pill is-checked">
                        <x-icon name="check" class="h-4 w-4" />
                        Checked in
                    </span>
                </div>
            </div>

            @if($property->latitude && $property->longitude)
                <div class="guest-portal-card mt-4 p-6">
                    <x-weather-badge :property="$property" />
                </div>
            @endif

            <div class="guest-portal-card mt-4">
                @if($locks->isNotEmpty())
                    <div class="p-6 pb-0">
                        <div class="grid gap-6 {{ $locks->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                            @foreach($locks as $entry)
                                <x-lock-card
                                    :booking-id="$booking->booking_id"
                                    :token="$booking->token"
                                    :lock-id="$entry['lock']->id"
                                    :lock-label="$locks->count() > 1 ? $entry['lock']->label : null"
                                    :lock-status="$entry['status']"
                                    :auto-checkin="! $booking->isMarkedCheckedIn()"
                                    :auto-checkout="$booking->isCheckoutDay()"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif
                <div id="guide-grid" class="guest-guide-grid p-6">
                    @foreach($guideCats as $category)
                        @php
                            $colors = $categoryColor;
                            $displayTitle = $category->pivot->custom_title ?: $category->title;
                            $displayDescription = $category->pivot->custom_description ?: $category->description;
                        @endphp
                        <x-guide-panel
                            :href="route('guest.category', [$booking->booking_id, $booking->token, $category])"
                            :icon="$category->slug"
                            :guest-icon="$category->guest_icon"
                            :title="$displayTitle"
                            :description="$displayDescription"
                            :tone="$colors[0]"
                            :accent="$colors[1]"
                            :wide="$category->slug === 'checkout-instructions'"
                        />
                    @endforeach
                </div>
            </div>

            <div class="guest-portal-card mt-4 p-6">
                <div class="guest-stay-grid">
                    <div class="guest-stay-tile">
                        <div class="guest-stay-tile-icon">
                            <x-icon name="guests" class="h-5 w-5" />
                        </div>
                        <p class="guest-stay-tile-label">Guest</p>
                        <p class="guest-stay-tile-date">{{ $booking->guest_name }}</p>
                    </div>
                    <div class="guest-stay-tile">
                        <div class="guest-stay-tile-icon">
                            <x-icon name="calendar" class="h-5 w-5" />
                        </div>
                        <p class="guest-stay-tile-label">Check-Out</p>
                        <p class="guest-stay-tile-date">{{ $booking->check_out_date->format('M d, Y') }} &middot; {{ $booking->effectiveCheckoutTimeFormatted() }}</p>
                    </div>
                </div>
            </div>
            </div>
        @endif
        <div class="px-6 py-5 text-center text-xs text-slate-400">
            <p>{{ \App\Models\Setting::getValue('site_copyright', '© Dreamzone Media LLC d/b/a Guest Hub') }}</p>
            <p class="mt-1">
                <a href="{{ route('legal.terms') }}" class="underline hover:text-slate-600">Terms of Service</a>
                &middot;
                <a href="{{ route('legal.privacy') }}" class="underline hover:text-slate-600">Privacy Policy</a>
                @if($booking->contract_accepted_at)
                &middot;
                <a href="{{ route('guest.rental-agreement', [$booking->booking_id, $booking->token]) }}" class="underline hover:text-slate-600" target="_blank" rel="noopener">Rental Agreement</a>
                @endif
                &middot;
                <a href="{{ route('contact') }}" class="underline hover:text-slate-600">Contact</a>
            </p>
        </div>
    </div>
</section>
</x-guest-layout>
