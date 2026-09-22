<x-admin-layout :title="$user->name">
    <div class="page-header">
        <div>
            <p class="eyebrow">Team Management</p>
            <h2 class="page-title">{{ $user->name }}</h2>
            <p class="page-subtitle">{{ $user->roleLabel() }} · {{ $user->email }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary">Edit account</a>
            <a href="{{ route('admin.users.index') }}" class="btn-ghost">← Team</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        {{-- Activity log --}}
        <section class="card">
            <div class="border-b border-slate-200 p-5">
                <h3 class="section-title">Recent activity</h3>
                <p class="section-copy">Last 20 actions by this user.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recentLogs as $log)
                    <div class="flex items-start gap-3 p-4 hover:bg-slate-50">
                        <span class="icon-chip mt-0.5 h-8 w-8 shrink-0">
                            <x-icon :name="$log->icon ?: 'logs'" class="h-3.5 w-3.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900">{{ $log->description }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                <span>{{ $log->created_at->diffForHumans() }}</span>
                                @if($log->module)
                                    <span class="badge badge-inactive">{{ $log->module }}</span>
                                @endif
                                <span class="badge {{ $log->severityClass() }}">{{ $log->severity }}</span>
                                @if($log->ip_address)
                                    <span>{{ $log->ip_address }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('admin.logs.show', $log) }}"
                           class="shrink-0 text-xs text-slate-400 hover:text-slate-700">Detail →</a>
                    </div>
                @empty
                    <div class="py-12 text-center">
                        <span class="icon-chip mx-auto mb-4 h-12 w-12"><x-icon name="logs" class="h-6 w-6" /></span>
                        <p class="text-sm text-slate-500">No activity recorded yet.</p>
                    </div>
                @endforelse
            </div>
            @if(count($recentLogs) >= 20)
                <div class="border-t border-slate-100 p-4">
                    <a href="{{ route('admin.logs.index') }}" class="text-sm font-semibold text-[#102338] hover:underline">
                        View all logs →
                    </a>
                </div>
            @endif
        </section>

        {{-- Profile sidebar --}}
        <aside class="grid gap-4 self-start">
            {{-- Profile card --}}
            <div class="card card-pad text-center">
                <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#102338] text-xl font-bold text-white">
                    {{ $user->initials() }}
                </span>
                <h3 class="mt-3 text-lg font-semibold text-slate-950">{{ $user->name }}</h3>
                <p class="text-sm text-slate-500">{{ $user->email }}</p>
                @if($user->phone)
                    <p class="text-sm text-slate-500">{{ $user->formatted_phone }}</p>
                @endif
                <div class="mt-3 flex justify-center gap-2">
                    <span class="badge {{ match($user->role) { 'owner' => 'border-purple-200 bg-purple-50 text-purple-700', 'manager' => 'border-blue-200 bg-blue-50 text-blue-700', 'staff' => 'badge-id_uploaded', default => 'badge-inactive' } }}">
                        {{ $user->roleLabel() }}
                    </span>
                    <span class="badge {{ $user->isActive() ? 'badge-active' : 'badge-inactive' }}">
                        {{ $user->isActive() ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            {{-- Account details --}}
            <div class="card card-pad">
                <h4 class="section-title">Account details</h4>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="font-semibold text-slate-600">Last login</dt>
                        <dd class="text-right text-slate-700">
                            {{ $user->last_login_at?->copy()->setTimezone(config('app.display_timezone'))->format('d M Y H:i') ?? 'Never' }}
                        </dd>
                    </div>
                    @if($user->last_login_ip)
                        <div class="flex justify-between">
                            <dt class="font-semibold text-slate-600">Last IP</dt>
                            <dd class="text-right font-mono text-slate-700">{{ $user->last_login_ip }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt class="font-semibold text-slate-600">Member since</dt>
                        <dd class="text-right text-slate-700">{{ $user->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-semibold text-slate-600">Tour completed</dt>
                        <dd class="text-right text-slate-700">
                            {{ $user->admin_tour_completed_at ? $user->admin_tour_completed_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y') : 'Not yet' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Permissions --}}
            <div class="card card-pad">
                <h4 class="section-title">Permissions</h4>
                <div class="mt-4 grid gap-2 text-sm">
                    @foreach([
                        ['Manage users',      $user->canManageUsers()],
                        ['Manage settings',   $user->canManageSettings()],
                        ['View logs',         $user->canViewLogs()],
                        ['Manage properties', $user->canManageProperties()],
                        ['Manage guests',     $user->canManageGuests()],
                        ['Delete data',       $user->canDeleteData()],
                    ] as [$perm, $allowed])
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                            <span class="text-slate-700">{{ $perm }}</span>
                            @if($allowed)
                                <x-icon name="check" class="h-4 w-4 text-emerald-500" />
                            @else
                                <x-icon name="x" class="h-4 w-4 text-slate-300" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Notes --}}
            @if($user->notes)
                <div class="card card-pad">
                    <h4 class="section-title">Notes</h4>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $user->notes }}</p>
                </div>
            @endif

            {{-- Actions --}}
            @if($user->id !== auth()->id())
                <div class="grid gap-2">
                    <form method="post" action="{{ route('admin.users.toggle-status', $user) }}">
                        @csrf
                        <button type="submit"
                                class="w-full {{ $user->isActive() ? 'btn-secondary text-amber-700' : 'btn-accent' }}">
                            {{ $user->isActive() ? 'Deactivate account' : 'Activate account' }}
                        </button>
                    </form>
                    @if(! $user->isOwner())
                        <form method="post" action="{{ route('admin.users.destroy', $user) }}"
                              onsubmit="return confirm('Permanently delete {{ $user->name }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger w-full">Delete account</button>
                        </form>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</x-admin-layout>
