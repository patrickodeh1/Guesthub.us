<x-admin-layout title="Channels">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <a href="{{ route('admin.properties.listing.edit', $property) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">&larr; Direct booking listing</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Channels &middot; {{ $property->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Edit your listing once in GuestHub and publish it to Airbnb and Booking.com. Calendar and rates sync separately.</p>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
        @endif
        @unless($featureEnabled)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">OTA channel sync is an optional subscription add-on, billed per active OTA listing. Enable it under <a class="font-semibold underline" href="{{ route('admin.plan.index') }}">Settings → Plan</a> before connecting or publishing channels.</div>
        @endunless
        @unless($featureEnabled)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">OTA channel sync is an optional subscription add-on, billed per active OTA listing. Enable it under <a class="font-semibold underline" href="{{ route('admin.plan.index') }}">Settings → Plan</a> before connecting or publishing channels.</div>
        @endunless
        @unless($configured)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Channel sync is not switched on for this server yet (set <code>REPULL_ENABLED</code> and <code>REPULL_API_KEY</code>).</div>
        @endunless
        @unless($listing)
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">Create the direct booking listing first, then come back here.</div>
        @endunless

        @foreach(['airbnb' => 'Airbnb', 'booking' => 'Booking.com', 'vrbo' => 'Vrbo'] as $channel => $label)
            @php
                $link = $links->get($channel);
                $accounts = $connections->get($channel, collect());
                $cap = $capabilities[$channel];
                $state = $link?->state ?? 'not_connected';
                $badge = match ($state) {
                    'live' => 'bg-emerald-100 text-emerald-800',
                    'published' => 'bg-sky-100 text-sky-800',
                    'publishing' => 'bg-amber-100 text-amber-800',
                    'partial' => 'bg-orange-100 text-orange-800',
                    'error' => 'bg-rose-100 text-rose-800',
                    default => 'bg-slate-100 text-slate-700',
                };
                $gaps = $missing[$channel] ?? [];
            @endphp
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $label }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $cap['note'] }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">{{ str_replace('_', ' ', ucfirst($state)) }}</span>
                </div>

                <div class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                    <div>
                        <div class="font-semibold text-slate-700">Connected account</div>
                        @forelse($accounts as $account)
                            <div class="text-slate-600">{{ $account->display_name ?: 'Account '.$account->external_account_id }}@if($account->access_type)<span class="ml-1 text-xs text-slate-500">({{ str_replace('_', ' ', $account->access_type) }})</span>@endif</div>
                        @empty
                            <div class="text-slate-500">Not connected</div>
                        @endforelse
                    </div>
                    <div>
                        <div class="font-semibold text-slate-700">Last sync</div>
                        <div class="text-slate-600">{{ $link?->last_synced_at ? $link->last_synced_at->diffForHumans() : 'Never' }}</div>
                    </div>
                </div>

                @if($link && $link->last_error)
                    <div class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $link->last_error }}</div>
                @endif
                @if($link && !empty($link->locked_fields))
                    <div class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">These fields are managed on {{ $label }} itself and were not changed: {{ implode(', ', $link->locked_fields) }}. Edit them directly on {{ $label }}.</div>
                @endif
                @if($link && !empty($link->warnings))
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-slate-600">
                        @foreach($link->warnings as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                @endif
                @if($link && !empty($link->sections))
                    <div class="mt-3 text-xs text-slate-500">Sections sent: {{ implode(', ', $link->sections) }}</div>
                @endif
                @if(!empty($gaps) && $cap['publish'])
                    <div class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">To publish, add: {{ implode(', ', $gaps) }}.</div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if($cap['connect'] && $accounts->isEmpty())
                        <form method="post" action="{{ route('admin.properties.channels.connect', [$property, $channel]) }}">@csrf
                            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Connect {{ $label }}</button>
                        </form>
                    @endif
                    @if($cap['publish'] && $accounts->isNotEmpty() && $listing)
                        <form method="post" action="{{ route('admin.properties.channels.publish', [$property, $channel]) }}">@csrf
                            <button @disabled(!empty($gaps)) class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">{{ $link && $link->external_listing_id ? 'Update on '.$label : 'Publish to '.$label }}</button>
                        </form>
                        @if($link && in_array($state, ['live', 'published', 'partial']))
                            <form method="post" action="{{ route('admin.properties.channels.unlist', [$property, $channel]) }}" onsubmit="return confirm('Take this listing off {{ $label }}?')">@csrf
                                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Take off market</button>
                            </form>
                        @endif
                    @endif
                </div>
            </section>
        @endforeach

        @if($listing && $listing->repull_listing_id)
            <form method="post" action="{{ route('admin.properties.channels.readiness', $property) }}">@csrf
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Check channel readiness</button>
            </form>
        @endif
    </div>
</x-admin-layout>
