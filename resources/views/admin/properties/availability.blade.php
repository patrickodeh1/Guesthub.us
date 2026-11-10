<x-admin-layout :title="$property->name.' - Availability'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Property setup</p>
            <h1 class="page-title">Availability</h1>
            <p class="page-subtitle">{{ $property->name }}</p>
        </div>
        <a href="{{ route('admin.properties.edit', $property) }}" class="btn-secondary">Back to property</a>
    </div>

    <div id="flash-area">
        @if(session('success'))
            <div data-flash="success" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div data-flash="error" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
        @endif
    </div>

    @php
        $seeded = (bool) $property->channex_availability_seeded_at;
        $days = $availabilities->mapWithKeys(fn ($r) => [
            $r->date->format('Y-m-d') => [
                's' => $r->status === 'booked' ? 'booked' : ($r->is_available ? 'open' : 'blocked'),
                'r' => $r->rate !== null ? (float) $r->rate : null,
            ],
        ]);
        $bookedCount = $availabilities->where('status', 'booked')->count();
    @endphp

    <div class="grid gap-6" data-avail-page>

        {{-- Sync status --}}
        <section id="sync-status" class="card card-pad">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-700">
                <span><strong>{{ $pendingCount }}</strong> change(s) waiting to send to Channex</span>
                <span class="{{ $failedCount ? 'font-semibold text-red-700' : '' }}"><strong>{{ $failedCount }}</strong> failed</span>
                @if($property->pricing_provider === 'wheelhouse')
                    <span>Last Wheelhouse pull:
                        <strong>{{ $property->wheelhouse_synced_at ? $property->wheelhouse_synced_at->diffForHumans() : 'never' }}</strong></span>
                @else
                    <span>Last PriceLabs pull:
                        <strong>{{ $property->pricelabs_synced_at ? \Carbon\Carbon::parse($property->pricelabs_synced_at)->diffForHumans() : 'never' }}</strong></span>
                @endif
            </div>
            @if($failedCount)
                <p class="field-help mt-2 text-red-700">Some changes failed after 10 tries and are no longer retried. @if($lastOutboxError) Last error: {{ $lastOutboxError }}@endif</p>
                <form method="post" action="{{ route('admin.properties.availability.retry-failed', $property) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn-secondary">Retry failed</button>
                </form>
            @endif
        </section>

        {{-- Calendar --}}
        <section class="card card-pad" x-data="availCal(@js($days), '{{ now()->toDateString() }}')" @avail-refresh.window="days = $event.detail; clear()">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-secondary" @click="prev()" :disabled="!canPrev()">&larr;</button>
                    <h2 class="section-title min-w-[10rem] text-center" x-text="title()"></h2>
                    <button type="button" class="btn-secondary" @click="next()">&rarr;</button>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-100 ring-1 ring-emerald-300"></span>Available</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-violet-300 ring-1 ring-violet-500"></span>Booked</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-red-100 ring-1 ring-red-300"></span>Blocked</span>
                </div>
            </div>

            <p id="cal-counts" class="field-help mt-2">{{ $blockedCount }} blocked &middot; {{ $bookedCount }} booked in the synced period. Nightly rates come from the selected provider or the listing base rate.</p>

            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs font-bold uppercase tracking-wide text-slate-500">
                <template x-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"><div x-text="d"></div></template>
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1">
                <template x-for="c in cells()" :key="c.key">
                    <div>
                        <template x-if="c.blank"><div class="h-16"></div></template>
                        <template x-if="!c.blank">
                            <button type="button"
                                @click="pick(c.date)"
                                :disabled="c.past"
                                :class="[cls(c), picked(c.date) ? 'ring-2 ring-slate-900' : '', c.past ? 'opacity-40 cursor-not-allowed' : 'hover:brightness-95']"
                                class="flex h-16 w-full flex-col items-center justify-center rounded-lg text-sm transition">
                                <span class="font-semibold" x-text="c.day"></span>
                                <span class="text-[11px] text-slate-600" x-text="c.rate ? '$' + Math.round(c.rate) : ''"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <form method="post" action="{{ route('admin.properties.availability.update-range', $property) }}" class="mt-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                @csrf
                <input type="hidden" name="date_from" :value="start">
                <input type="hidden" name="date_to" :value="end || start">
                <input type="hidden" name="availability" value="">
                <div class="flex-1 min-w-[220px] text-sm text-slate-700">
                    <template x-if="!start"><span>Click a date, then a second date to select a range.</span></template>
                    <template x-if="start"><span><strong x-text="label()"></strong></span></template>
                </div>
                <button type="button" class="btn-primary" :disabled="!start" @click="go('block', $event)">Block</button>
                <button type="button" class="btn-secondary" :disabled="!start" @click="go('open', $event)">Open</button>
                <button type="button" class="btn-secondary" @click="clear()" x-show="start">Clear</button>
            </form>
            <p class="field-help mt-2">Changes reach Channex within about a minute. Booked nights stay closed.</p>
        </section>

        {{-- Rates and restrictions --}}
        <section class="card card-pad">
            <h2 class="section-title">Rates and restrictions</h2>
            <p class="section-copy">Set a rate or stay rules for a date range. Only the fields you fill in change. Manual rates are protected from provider updates. Changes reach Channex within about a minute when this property is mapped there.</p>
            <form method="post" action="{{ route('admin.properties.availability.update-range', $property) }}" class="mt-4 grid gap-4">
                @csrf
                <div class="flex flex-wrap gap-3">
                    <label class="field-label w-44">From
                        <input type="date" name="date_from" value="{{ old('date_from') }}" min="{{ now()->toDateString() }}" class="input" required>
                    </label>
                    <label class="field-label w-44">To
                        <input type="date" name="date_to" value="{{ old('date_to') }}" min="{{ now()->toDateString() }}" class="input" required>
                    </label>
                    <label class="field-label w-32">Rate
                        <input type="number" name="rate" value="{{ old('rate') }}" min="0.01" step="0.01" class="input" placeholder="No change">
                    </label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="field-label w-36">Min stay (arrival)
                        <input type="number" name="min_stay_arrival" value="{{ old('min_stay_arrival') }}" min="1" max="365" class="input" placeholder="No change">
                    </label>
                    <label class="field-label w-36">Min stay (through)
                        <input type="number" name="min_stay_through" value="{{ old('min_stay_through') }}" min="1" max="365" class="input" placeholder="No change">
                    </label>
                    <label class="field-label w-36">Max stay
                        <input type="number" name="max_stay" value="{{ old('max_stay') }}" min="1" max="365" class="input" placeholder="No change">
                    </label>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach(['stop_sell' => 'Stop sell', 'closed_to_arrival' => 'Closed to arrival', 'closed_to_departure' => 'Closed to departure'] as $field => $label)
                        <label class="field-label w-44">{{ $label }}
                            <select name="{{ $field }}" class="input">
                                <option value="">No change</option>
                                <option value="1" @selected(old($field) === '1')>On</option>
                                <option value="0" @selected(old($field) === '0')>Off</option>
                            </select>
                        </label>
                    @endforeach
                </div>
                <div><button type="submit" class="btn-primary">Save rates and restrictions</button></div>
            </form>
            <form method="post" action="{{ route('admin.properties.availability.release-manual', $property) }}" class="mt-3" onsubmit="return confirm('Hand every manually set night back to the selected pricing provider? Its next pull may overwrite them.')">
                @csrf
                <button type="submit" class="btn-secondary">Release manual rates to provider</button>
                <span class="section-copy ml-2">Manual rates stay protected until you release them.</span>
            </form>
        </section>

        {{-- Channex push log --}}
        <details class="card card-pad">
            <summary class="section-title cursor-pointer">Channex push log</summary>
            <p class="section-copy mt-2">The last 30 updates sent to Channex. The task ID is what Channex asks for in the certification form.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr><th class="py-1 pr-4">Time</th><th class="py-1 pr-4">Type</th><th class="py-1 pr-4">Ranges</th><th class="py-1 pr-4">Result</th><th class="py-1">Task ID</th></tr>
                    </thead>
                    <tbody id="push-log-body">
                        @forelse($pushLog as $row)
                            <tr class="border-t border-slate-200 align-top">
                                <td class="py-1 pr-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->created_at)->format('M j, g:i:s a') }}</td>
                                <td class="py-1 pr-4">{{ $row->path }}</td>
                                <td class="py-1 pr-4">{{ $row->value_count }}</td>
                                <td class="py-1 pr-4 {{ $row->success ? 'text-emerald-700' : 'text-red-700' }}">{{ $row->success ? 'Sent' : 'Failed ('.$row->status.')' }}</td>
                                <td class="py-1 font-mono text-xs break-all">{{ $row->task_ids ?: ($row->error ?: '') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-slate-500">Nothing sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>

        {{-- Settings --}}
        <details id="settings" class="card card-pad" @if(session('mappingOptions') || session('ratePlanOptions')) open @endif>
            <summary class="section-title cursor-pointer">Settings</summary>

            <div id="settings-body" class="mt-5 grid gap-8">

                {{-- Calendar link --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Calendar link (iCal)</h3>
                    <p class="section-copy">Import blocked and booked dates from an iCal feed. Use this for the first import, or to re-check a calendar.</p>

                    @if($calendarExportEnabled && $calendarExportUrl)
                        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4">
                            <h4 class="font-semibold text-slate-900">Export to Airbnb, Vrbo, or another calendar</h4>
                            <p class="section-copy mt-1">Copy this private link into the other service's calendar-import settings. It contains reservations and manual blocks as anonymous “Reserved” events.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <input id="calendar-export-url" type="url" readonly value="{{ $calendarExportUrl }}" class="input min-w-[280px] flex-1">
                                <button type="button" class="btn-secondary" data-copy-calendar>Copy link</button>
                            </div>
                            <p class="field-help mt-3">In Airbnb or Vrbo, open calendar sync/import, choose to import a calendar, paste this link, and save. These services refresh iCal feeds on their own schedule, so changes are not real-time. Adjust advance notice and same-day cutoff in the listing settings to reduce overlap risk.</p>
                            <p class="field-help mt-1">Treat this URL like a password. Anyone with the link can see that dates are reserved, but no guest details are included.</p>
                        </div>
                    @else
                        <p class="field-help mt-3">Calendar export is not enabled for this host.</p>
                    @endif

                    <form method="post" action="{{ route('admin.properties.availability.save-ical-url', $property) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label flex-1 min-w-[280px]">iCal URL
                            <input type="url" name="airbnb_ical_url" value="{{ old('airbnb_ical_url', $property->airbnb_ical_url) }}" placeholder="https://.../calendar.ics" class="input">
                        </label>
                        <button type="submit" class="btn-secondary">Save link</button>
                    </form>

                    <form method="post" action="{{ route('admin.properties.availability.import-ical', $property) }}" class="mt-3" x-data="{ ok: {{ $seeded ? 'false' : 'true' }} }">
                        @csrf
                        @if($seeded)
                            <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="reseed" value="1" x-model="ok"> Re-import (replaces the dates currently stored)</label>
                        @endif
                        <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="confirm_high_block" value="1"> Import even if most dates look blocked</label>
                        <button type="submit" class="btn-primary" :disabled="{{ $property->airbnb_ical_url ? '!ok' : 'true' }}">Import from calendar link</button>
                        @unless($property->airbnb_ical_url)
                            <span class="field-help ml-2">Save a link above first.</span>
                        @endunless
                    </form>
                </div>

                {{-- Channex room type --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Channex room type</h3>
                    <p class="section-copy">Required to send availability to Channex.</p>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-sm {{ $isMapped ? 'text-emerald-700' : 'text-amber-700' }} font-medium">{{ $isMapped ? 'Mapped' : 'Not mapped yet' }}</span>
                        @if($isMapped)
                            <span class="field-help">Room type ID: {{ $property->channex_room_type_id }}</span>
                        @endif
                    </div>

                    <form method="post" action="{{ route('admin.properties.availability.fetch-mapping', $property) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary" {{ $property->channex_property_id ? '' : 'disabled' }}>Fetch room types from Channex</button>
                        @unless($property->channex_property_id)
                            <span class="field-help ml-2">Set the Channex Property ID on the main property form first.</span>
                        @endunless
                    </form>

                    @if(session('mappingOptions'))
                        <form method="post" action="{{ route('admin.properties.availability.save-mapping', $property) }}" class="mt-3 grid gap-3">
                            @csrf
                            <label class="field-label">Choose the room type
                                <select name="channex_room_type_id" class="input">
                                    <option value="">— Select —</option>
                                    @foreach(session('mappingOptions') as $option)
                                        <option value="{{ $option['room_type_id'] }}" {{ $property->channex_room_type_id === $option['room_type_id'] ? 'selected' : '' }}>{{ $option['room_type_title'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn-primary w-fit">Save mapping</button>
                        </form>
                    @endif
                </div>

                {{-- Rate plan --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Rate plan</h3>
                    <p class="section-copy">Rates and minimum stays come from the selected pricing provider. Choose the Channex rate plan only when this property sends rates through Channex.</p>

                    <form method="post" action="{{ route('admin.properties.availability.fetch-rate-plans', $property) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary" {{ $isMapped ? '' : 'disabled' }}>Fetch rate plans from Channex</button>
                        @unless($isMapped)
                            <span class="field-help ml-2">Map the room type first.</span>
                        @endunless
                        @if($property->channex_rate_plan_id)
                            <span class="field-help ml-2">Rate plan ID: {{ $property->channex_rate_plan_id }}</span>
                        @endif
                    </form>

                    @if(session('ratePlanOptions'))
                        <form method="post" action="{{ route('admin.properties.availability.save-rate-settings', $property) }}" class="mt-3 grid gap-3">
                            @csrf
                            <label class="field-label">Rate plan
                                <select name="channex_rate_plan_id" class="input">
                                    <option value="">— Select —</option>
                                    @foreach(session('ratePlanOptions') as $option)
                                        <option value="{{ $option['rate_plan_id'] }}" {{ $property->channex_rate_plan_id === $option['rate_plan_id'] ? 'selected' : '' }}>{{ $option['rate_plan_title'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn-primary w-fit">Save rate plan</button>
                        </form>
                    @endif
                </div>

                {{-- Booking window --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Booking window</h3>
                    <p class="section-copy">Close dates more than this many days ahead. Leave blank to manage by hand. Dates you open yourself stay open, and your manual blocks are never changed.</p>
                    <form method="post" action="{{ route('admin.properties.availability.save-window', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label w-48">Days ahead
                            <input type="number" name="close_ahead_days" min="1" max="499" value="{{ old('close_ahead_days', $property->close_ahead_days) }}" placeholder="Off" class="input">
                        </label>
                        <button type="submit" class="btn-secondary">Save</button>
                    </form>
                </div>

                {{-- Pricing provider --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Pricing provider</h3>
                    <p class="section-copy">Choose one source at a time. Switching clears rates owned by the previous provider; manual overrides remain. Quotes use the listing base rate until the selected provider supplies a rate.</p>
                    <form method="post" action="{{ route('admin.properties.availability.save-pricing-provider', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label w-56">Active provider
                            <select name="pricing_provider" class="input">
                                <option value="" @selected(! $property->pricing_provider)>Listing base rate</option>
                                <option value="pricelabs" @selected($property->pricing_provider === 'pricelabs')>PriceLabs</option>
                                <option value="wheelhouse" @selected($property->pricing_provider === 'wheelhouse')>Wheelhouse</option>
                            </select>
                        </label>
                        <button type="submit" class="btn-secondary">Save provider</button>
                    </form>

                    <h4 class="mt-6 font-semibold text-slate-900">PriceLabs</h4>
                    <p class="section-copy mt-1">Host API keys take precedence over the platform key. Keys are encrypted at rest; they are never shown again after saving.</p>
                    <p class="field-help mt-1">
                        @if($priceLabsHostKeySet)
                            A host key is saved.
                        @elseif($priceLabsPlatformKeySet)
                            Using the configured platform key.
                        @else
                            No PriceLabs key is configured.
                        @endif
                    </p>
                    <form method="post" action="{{ route('admin.properties.availability.verify-pricelabs', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label min-w-[260px] flex-1">PriceLabs API key
                            <input type="password" name="pricelabs_api_key" autocomplete="new-password" class="input" placeholder="{{ $priceLabsKeySet ? 'Leave blank to use the saved key' : 'Paste API key' }}">
                        </label>
                        <button type="submit" class="btn-secondary">Verify and load listings</button>
                    </form>
                    @if(session('priceLabsListings'))
                        <form method="post" action="{{ route('admin.properties.availability.save-pricelabs', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <label class="field-label min-w-[280px] flex-1">PriceLabs listing
                                <select name="pricelabs_selection" class="input" required>
                                    <option value="">— Select listing —</option>
                                    @foreach(session('priceLabsListings') as $option)
                                        <option value="{{ $option['id'].'|'.$option['pms'] }}">{{ $option['name'] }} ({{ $option['pms'] }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn-secondary">Save listing</button>
                        </form>
                    @elseif($property->pricelabs_listing_id)
                        <p class="field-help mt-2">Linked listing: {{ $property->pricelabs_listing_id }} ({{ $property->pricelabs_pms }})</p>
                    @endif

                    <form method="post" action="{{ route('admin.properties.availability.sync-pricing', $property) }}" class="mt-3">
                        @csrf
                        @php
                            $activeProviderReady = ($property->pricing_provider === 'pricelabs' && $priceLabsKeySet && $property->pricelabs_listing_id)
                                || ($property->pricing_provider === 'wheelhouse' && $wheelhousePartnerKeySet && $wheelhouseUserKeySet && $property->wheelhouse_listing_id);
                        @endphp
                        <button type="submit" class="btn-primary" {{ $activeProviderReady ? '' : 'disabled' }}>Pull active provider rates now</button>
                        @if($property->pricing_provider === 'pricelabs' && $property->pricelabs_synced_at)
                            <span class="field-help ml-2">Last pull {{ \Carbon\Carbon::parse($property->pricelabs_synced_at)->diffForHumans() }}</span>
                        @elseif($property->pricing_provider === 'wheelhouse' && $property->wheelhouse_synced_at)
                            <span class="field-help ml-2">Last pull {{ $property->wheelhouse_synced_at->diffForHumans() }}</span>
                        @endif
                    </form>

                    <h4 class="mt-6 font-semibold text-slate-900">Wheelhouse</h4>
                    @if($wheelhousePartnerKeySet)
                        <p class="section-copy mt-1">Connect an existing Wheelhouse account with its User API Key, or create/update a GuestHub-managed Wheelhouse listing.</p>
                    @else
                        <p class="field-help mt-1">GuestHub’s Wheelhouse partner key is not configured. Existing account access and integrator registration are unavailable until credentials are added.</p>
                    @endif
                    @if($wheelhouseUserKeySet)
                        <p class="field-help mt-2">A Wheelhouse User API Key is saved for this host.</p>
                    @endif
                    <form method="post" action="{{ route('admin.properties.availability.save-wheelhouse-key', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label min-w-[260px] flex-1">Wheelhouse User API Key
                            <input type="password" name="wheelhouse_user_api_key" autocomplete="new-password" class="input" placeholder="Paste an existing-account key" required>
                        </label>
                        <button type="submit" class="btn-secondary" {{ $wheelhousePartnerKeySet ? '' : 'disabled' }}>Verify existing account</button>
                    </form>
                    @if(session('wheelhouseListings'))
                        <form method="post" action="{{ route('admin.properties.availability.save-wheelhouse-listing', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <label class="field-label min-w-[280px] flex-1">Existing Wheelhouse listing
                                <select name="wheelhouse_selection" class="input" required>
                                    <option value="">— Select listing —</option>
                                    @foreach(session('wheelhouseListings') as $option)
                                        <option value="{{ $option['id'].'|'.$option['channel'] }}">{{ $option['title'] }} ({{ $option['channel'] }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn-secondary">Save listing</button>
                        </form>
                    @elseif($property->wheelhouse_listing_id)
                        <p class="field-help mt-2">Linked listing: {{ $property->wheelhouse_listing_id }}{{ $property->wheelhouse_channel ? ' ('.$property->wheelhouse_channel.')' : ' (GuestHub-managed)' }}</p>
                    @endif
                    <form method="post" action="{{ route('admin.properties.availability.connect-wheelhouse', $property) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary" {{ $wheelhousePartnerKeySet ? '' : 'disabled' }}>Create/update GuestHub-managed Wheelhouse listing</button>
                    </form>
                </div>

                {{-- Sync tools --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Sync to Channex</h3>
                    <p class="section-copy">Full sync sends the next 500 days of availability and rates in one call each. Use it for certification or to repair a mismatch.</p>

                    <form method="post" action="{{ route('admin.properties.availability.full-sync', $property) }}" class="mt-3" onsubmit="return confirm('Send the next 500 days to Channex now?');">
                        @csrf
                        <button type="submit" class="btn-primary" {{ $isMapped ? '' : 'disabled' }}>Run full sync</button>
                        @unless($isMapped)
                            <span class="field-help ml-2">Map the Channex room type first.</span>
                        @endunless
                    </form>

                    <form method="post" action="{{ route('admin.properties.availability.push-to-channex', $property) }}" class="mt-4" x-data="{ ok: {{ $seeded ? 'false' : 'true' }} }">
                        @csrf
                        @if($seeded)
                            <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="reseed" value="1" x-model="ok"> Overwrite Channex's current dates, including any bookings made since</label>
                        @endif
                        <button type="submit" class="btn-secondary" :disabled="{{ ($isMapped && $availabilities->isNotEmpty()) ? '!ok' : 'true' }}">Initial push of stored dates</button>
                        <span class="field-help ml-2">First-time setup only.</span>
                    </form>
                </div>

            </div>
        </details>
    </div>

    <script type="application/json" id="avail-days">@json($days)</script>

    @include('admin.properties._availability-ajax')

    <script>
        document.querySelector('[data-copy-calendar]')?.addEventListener('click', async event => {
            const button = event.currentTarget;
            const input = document.getElementById('calendar-export-url');
            try {
                await navigator.clipboard.writeText(input.value);
                button.textContent = 'Copied';
            } catch {
                input.select();
                button.textContent = document.execCommand('copy') ? 'Copied' : 'Select and copy';
            }
        });

        function availCal(days, today) {
            const t = new Date(today + 'T00:00:00');
            const pad = n => String(n).padStart(2, '0');
            const fmt = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
            return {
                days, today, y: t.getFullYear(), m: t.getMonth(), start: null, end: null,
                title() { return new Date(this.y, this.m, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' }); },
                canPrev() { return this.y > t.getFullYear() || this.m > t.getMonth(); },
                prev() { if (!this.canPrev()) return; this.m--; if (this.m < 0) { this.m = 11; this.y--; } },
                next() { this.m++; if (this.m > 11) { this.m = 0; this.y++; } },
                cells() {
                    const out = [], lead = new Date(this.y, this.m, 1).getDay(), n = new Date(this.y, this.m + 1, 0).getDate();
                    for (let i = 0; i < lead; i++) out.push({ key: 'b' + i, blank: true });
                    for (let d = 1; d <= n; d++) {
                        const date = fmt(this.y, this.m, d), row = this.days[date] || null;
                        out.push({ key: date, blank: false, day: d, date, past: date < this.today, state: row ? row.s : null, rate: row ? row.r : null });
                    }
                    return out;
                },
                cls(c) {
                    if (c.state === 'booked') return 'bg-violet-300 text-violet-950 font-semibold';
                    if (c.state === 'blocked') return 'bg-red-100 text-red-900';
                    if (c.state === 'open') return 'bg-emerald-100 text-emerald-900';
                    return 'bg-slate-50 text-slate-500';
                },
                pick(d) {
                    if (!this.start || this.end) { this.start = d; this.end = null; return; }
                    if (d < this.start) { this.end = this.start; this.start = d; } else { this.end = d; }
                },
                picked(d) { return this.start && (this.end ? d >= this.start && d <= this.end : d === this.start); },
                clear() { this.start = null; this.end = null; },
                go(mode, e) { const f = e.target.closest('form'); f.querySelector('[name=availability]').value = mode; f.requestSubmit(); },
                label() {
                    const f = s => new Date(s + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    return this.end && this.end !== this.start ? f(this.start) + ' to ' + f(this.end) + ' (last night)' : f(this.start);
                },
            };
        }
    </script>
</x-admin-layout>
