@php
    $__beyond = \Illuminate\Support\Carbon::now(config('app.display_timezone'))->startOfDay()->addDays(10)->toDateString();
    $__further = collect($unscheduledCheckouts ?? [])
        ->filter(fn ($c) => \Illuminate\Support\Carbon::parse($c['checkout_date'])->toDateString() > $__beyond)
        ->values();
@endphp
@if($__further->isNotEmpty())
    <section class="mt-4 overflow-hidden rounded-xl border border-orange-200 bg-orange-50">
        <h2 class="border-b border-orange-200 bg-orange-100 px-5 py-3 text-lg font-bold text-orange-900">Further out: checkouts without a cleaner</h2>
        <ul class="divide-y divide-orange-100">
            @foreach($__further as $checkout)
                <li class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-base font-semibold text-orange-950">{{ \Illuminate\Support\Carbon::parse($checkout['checkout_date'])->format('l, M j') }}</p>
                        <p class="text-base text-orange-900">{{ $checkout['property_name'] }}</p>
                    </div>
                    <a href="{{ route('manage.sessions.create', ['property_id' => $checkout['property_id'], 'date' => $checkout['checkout_date']]) }}"
                       class="inline-flex items-center justify-center rounded-md bg-orange-600 px-4 py-2 text-base font-semibold text-white hover:bg-orange-700">Schedule Cleaning</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
