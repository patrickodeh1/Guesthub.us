<x-admin-layout title="Payments">
    <div class="page-header">
        <div>
            <p class="eyebrow">Payments</p>
            <h2 class="page-title">Payments</h2>
            <p class="page-subtitle">Deposit, parking, incidentals, early check-in and late checkout charges collected via Stripe.</p>
        </div>
    </div>

    @unless($stripeConfigured)
        <div class="card card-pad mb-5 border-amber-200 bg-amber-50">
            <p class="text-sm font-semibold text-amber-800">Stripe isn't configured yet</p>
            <p class="mt-1 text-sm text-amber-700">Guests won't be able to pay a deposit or any other charge online until STRIPE_KEY / STRIPE_SECRET are set.</p>
        </div>
    @endunless

    {{-- Filters --}}
    <form method="get" class="card card-pad mb-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1" style="min-width:180px">
                <label class="field-label">Search</label>
                <div class="relative mt-2">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input name="search" value="{{ request('search') }}" type="text" placeholder="Guest name, booking ID…" class="input pl-9">
                </div>
            </div>
            <div>
                <label class="field-label">Type</label>
                <select name="type" class="input mt-2">
                    <option value="">All types</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Status</label>
                <select name="status" class="input mt-2">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Date from</label>
                <input name="date_from" type="date" value="{{ request('date_from') }}" class="input mt-2">
            </div>
            <div>
                <label class="field-label">Date to</label>
                <input name="date_to" type="date" value="{{ request('date_to') }}" class="input mt-2">
            </div>
            <button type="submit" class="btn-primary">Filter</button>
            @if(request()->hasAny(['search', 'type', 'status', 'date_from', 'date_to']))
                <a href="{{ route('admin.payments.index') }}" class="btn-secondary">Clear</a>
            @endif
        </div>
    </form>

    {{-- Stats strip --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="card card-pad flex items-center gap-3">
            <span class="icon-chip h-10 w-10"><x-icon name="check" class="h-4 w-4" /></span>
            <div>
                <p class="text-xl font-semibold text-slate-950">${{ number_format($totals['success_cents'] / 100, 2) }}</p>
                <p class="text-xs text-slate-500">Success</p>
            </div>
        </div>
        <div class="card card-pad flex items-center gap-3">
            <span class="icon-chip h-10 w-10"><x-icon name="clock" class="h-4 w-4" /></span>
            <div>
                <p class="text-xl font-semibold text-slate-950">${{ number_format($totals['pending_cents'] / 100, 2) }}</p>
                <p class="text-xs text-slate-500">Pending</p>
            </div>
        </div>
        <div class="card card-pad flex items-center gap-3">
            <span class="icon-chip h-10 w-10"><x-icon name="security" class="h-4 w-4" /></span>
            <div>
                <p class="text-xl font-semibold text-slate-950">{{ number_format($totals['failed_count']) }}</p>
                <p class="text-xs text-slate-500">Failed</p>
            </div>
        </div>
    </div>

    {{-- Charges table --}}
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Guest / Booking</th>
                    <th>Property</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Billing moment</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charges as $charge)
                    <tr class="group hover:bg-slate-50/80">
                        <td>
                            @if($charge->booking)
                                <a href="{{ route('admin.guests.show', $charge->booking) }}" class="text-sm font-medium text-slate-900 hover:underline">{{ $charge->booking->guest_name }}</a>
                                <p class="text-xs text-slate-500">{{ $charge->booking->booking_id }}</p>
                            @else
                                <span class="text-slate-400">Booking deleted</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-700">{{ $charge->booking?->property?->name ?? '—' }}</td>
                        <td>
                            <span class="badge badge-inactive">{{ $types[$charge->type] ?? $charge->type }}</span>
                            @if($charge->description)
                                <p class="mt-1 text-xs text-slate-500">{{ $charge->description }}</p>
                            @endif
                        </td>
                        <td class="text-sm font-semibold text-slate-900">${{ number_format($charge->amount_cents / 100, 2) }}</td>
                        <td>
                            @php
                                $statusBadge = match($charge->status) {
                                    'success' => 'badge-active',
                                    'failed' => 'badge-pending',
                                    default => 'badge-inactive',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statuses[$charge->status] ?? $charge->status }}</span>
                        </td>
                        <td class="text-sm text-slate-500">{{ $charge->billing_moment ? str($charge->billing_moment)->replace('_', ' ')->title() : '—' }}</td>
                        <td class="text-sm text-slate-500">{{ $charge->created_at->copy()->setTimezone(config('app.display_timezone'))->format('M j, Y g:ia') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <span class="icon-chip mx-auto mb-4 h-14 w-14"><x-icon name="check" class="h-7 w-7" /></span>
                            <p class="text-base font-semibold text-slate-950">No payments yet</p>
                            <p class="mt-1 text-sm text-slate-500">Deposit and other guest charges will appear here once collected.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($charges->hasPages())
        <div class="mt-4">{{ $charges->links() }}</div>
    @endif
</x-admin-layout>
