<x-admin-layout title="Channels">
    @php($canManageAnyChannel = $connections->flatten()->contains(fn ($account) => $account->access_type === 'full_access'))
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <a href="{{ route('admin.properties.listing.edit', $property) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">&larr; Direct booking listing</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Channels &middot; {{ $property->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">Edit your listing once in GuestHub and publish it to Airbnb and Booking.com. Calendar and rates sync separately.</p>
        </div>

        @if($featureEnabled && !empty($airbnbListings) && !($listing && $listing->published_at))
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-900">Import an existing Airbnb listing</h2>
                <p class="mt-1 text-sm text-slate-600">Choose a listing already synced into Repull. GuestHub will pull its current Airbnb content into this unpublished draft, including photos and room details. This replaces the current draft content; it never overwrites a published GuestHub listing.</p>
                <form method="post" action="{{ route('admin.properties.channels.airbnb.import', $property) }}" class="mt-3 flex flex-wrap items-end gap-3" onsubmit="return confirm('Replace this unpublished GuestHub draft with the selected Airbnb listing?')">@csrf
                    <label class="field-label min-w-0 flex-1">Airbnb listing
                        <select name="selection" required class="input">
                            <option value="">Choose an Airbnb listing</option>
                            @foreach($airbnbListings as $remoteListing)
                                @foreach($remoteListing['connections'] as $remoteConnection)
                                    <option value="{{ $remoteListing['listingId'] }}|{{ $remoteConnection['id'] }}|{{ $remoteConnection['accountId'] }}">
                                        {{ $remoteListing['name'] ?? 'Airbnb listing '.$remoteListing['listingId'] }} · {{ $remoteConnection['airbnbId'] ?? '' }} · {{ $remoteConnection['accountName'] ?? 'Airbnb account' }}
                                    </option>
                                @endforeach
                            @endforeach
                        </select>
                    </label>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Import to draft</button>
                </form>
            </section>
        @endif

        @if(session('channel_results'))
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-900">Per-channel result</h2>
                <div class="mt-2 grid gap-2 text-sm">
                    @foreach(session('channel_results') as $result)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <p class="font-semibold">{{ ucfirst($result['channel'] ?? $result['platform'] ?? 'Channel') }} · {{ ($result['ok'] ?? false) ? 'Confirmed' : 'Not confirmed' }}</p>
                            @if(!empty($result['message']))<p class="mt-1 text-slate-600">{{ $result['message'] }}</p>@endif
                            @if(!empty($result['fix']))<p class="mt-1 text-amber-800">{{ $result['fix'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
        @endif
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
                $canManageChannel = $accounts->contains(fn ($account) => $account->access_type === 'full_access');
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

                @if($channel === 'airbnb' && $link && $link->status_checked_at)
                    <div class="mt-2 text-xs text-slate-500">Airbnb sync: {{ $link->sync_category ?: 'Unknown' }} · {{ $link->writable === null ? 'Not confirmed' : ($link->writable ? 'Writable' : 'Not writable') }} · checked {{ $link->status_checked_at->diffForHumans() }}</div>
                    @if($link->writable === false || $link->sync_category === 'none')
                        <div class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">Turn on API sync for this individual listing inside Airbnb. Reconnecting your account will not enable listing-level API sync.</div>
                    @endif
                @endif
                @if($state === 'published')
                    <div class="mt-2 rounded-md bg-sky-50 px-3 py-2 text-sm text-sky-900">Content is published, but this status does not confirm the listing is live and bookable. Check the channel's activation status.</div>
                @endif
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
                    @if($featureEnabled && $channel === 'airbnb' && $accounts->isNotEmpty())
                        <form method="post" action="{{ route('admin.properties.channels.airbnb.refresh-status', $property) }}">@csrf
                            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Refresh Airbnb sync status</button>
                        </form>
                    @endif
                    @if($featureEnabled && $cap['connect'] && ($accounts->isEmpty() || ! $canManageChannel))
                        <form method="post" action="{{ route('admin.properties.channels.connect', [$property, $channel]) }}" class="flex flex-wrap items-end gap-2">@csrf
                            @if($channel === 'airbnb')
                                <label class="field-label text-xs">Access level
                                    <select name="access_type" class="input">
                                        <option value="full_access">Full management</option>
                                        <option value="messaging">Messaging only (keep existing PMS; no content/calendar writes)</option>
                                        <option value="read_only">Read only (import)</option>
                                    </select>
                                </label>
                            @elseif($channel === 'vrbo')
                                <label class="field-label text-xs">Access level
                                    <select name="access_type" class="input">
                                        <option value="full_access">Full access (calendar and prices)</option>
                                        <option value="messaging">Messaging only (keep existing PMS)</option>
                                    </select>
                                </label>
                            @elseif($channel === 'booking')
                                <label class="field-label text-xs">Connection type
                                    <select name="connection_mode" class="input">
                                        <option value="standard">Booking.com partner connection (full management)</option>
                                        <option value="extranet">Booking.com Extranet login (messaging only)</option>
                                    </select>
                                </label>
                            @endif
                            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ $accounts->isEmpty() ? 'Connect' : 'Change access' }} {{ $label }}</button>
                        </form>
                    @endif
                    @if($featureEnabled && $cap['publish'] && $accounts->isNotEmpty() && $listing && ! $canManageChannel)
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">This account is messaging-only or read-only. GuestHub cannot publish, change calendars or unlist with this access level. Reconnect with full management when the host is ready to switch.</p>
                    @endif
                    @if($featureEnabled && $cap['publish'] && $accounts->isNotEmpty() && $listing && $canManageChannel)
                        <form method="post" action="{{ route('admin.properties.channels.publish', [$property, $channel]) }}">@csrf
                            @if($channel === 'booking')
                                <label class="field-label text-xs">Booking.com property ID (only if multiple)
                                    <input name="hotel_id" value="{{ old('hotel_id', $link?->external_listing_id) }}" class="input" placeholder="Repull/Booking property ID">
                                </label>
                            @endif
                            <button @disabled(!empty($gaps)) class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40">{{ $link && $link->external_listing_id ? 'Update on '.$label : 'Publish to '.$label }}</button>
                        </form>
                        @if($featureEnabled && $canManageChannel && $link && in_array($state, ['live', 'published', 'partial']))
                            <form method="post" action="{{ route('admin.properties.channels.unlist', [$property, $channel]) }}" onsubmit="return confirm('Take this listing off {{ $label }}?')">@csrf
                                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Take off market</button>
                            </form>
                        @endif
                    @endif
                </div>
            </section>
        @endforeach


        @if($featureEnabled && $bookingLink && $bookingLink->external_listing_id)
            <section class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-900">Booking.com licence details</h2>
                <p class="mt-1 text-sm text-slate-600">Some regions require a licence or registration number before Booking.com will open the property. Read the current requirements and enter the matching variant and fields below.</p>
                @if($bookingLicenceInfo)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">View Booking.com licence requirements</summary>
                        <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-slate-50 p-3 text-xs">{{ json_encode($bookingLicenceInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                @endif
                <form method="post" action="{{ route('admin.properties.channels.booking.licences', $property) }}" class="mt-4 grid gap-3">@csrf
                    <label class="field-label">Variant ID
                        <input type="number" min="1" name="variant_id" required class="input" placeholder="Variant ID from requirements">
                    </label>
                    <label class="field-label">Licence fields (JSON array)
                        <textarea name="content_data_json" rows="5" required class="textarea" placeholder='[{"name":"business_tax_receipt","value":"BTR123456"},{"name":"certificate_number","value":"1234567"}]'></textarea>
                        <span class="field-help">Use the exact field names returned by Booking.com. Do not guess a licence type or number format.</span>
                    </label>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Submit licence details</button>
                </form>
                @if(session('booking_licence_result'))
                    <pre class="mt-3 max-h-48 overflow-auto rounded-lg bg-slate-50 p-3 text-xs">{{ json_encode(session('booking_licence_result'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
            </section>
        @endif

        @if($featureEnabled && $listing && $listing->repull_listing_id && $canManageAnyChannel)
            <div class="flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.properties.channels.online-status', $property) }}">@csrf
                    <input type="hidden" name="online" value="0">
                    <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700" onclick="return confirm('Take this listing offline on every connected channel?')">Take offline everywhere</button>
                </form>
                <form method="post" action="{{ route('admin.properties.channels.online-status', $property) }}">@csrf
                    <input type="hidden" name="online" value="1">
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Put online everywhere</button>
                </form>
                <form method="post" action="{{ route('admin.properties.channels.readiness', $property) }}">@csrf
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Check channel readiness</button>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
