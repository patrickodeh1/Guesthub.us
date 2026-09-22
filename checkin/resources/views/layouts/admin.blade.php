<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} &middot; Welcome Guide</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $siteLogo = \App\Models\Setting::getValue('site_logo');
        $favicon = \App\Models\Setting::getValue('favicon');
    @endphp
    @if($favicon)
        <link rel="icon" href="{{ url('/img/'.$favicon) }}">
    @endif
</head>
<body class="bg-slate-50 text-slate-900 antialiased">

{{-- ══════════════════════════════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════════════════════════ --}}
<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-30 hidden w-64 overflow-x-hidden overflow-y-auto bg-[#082b49] text-white shadow-xl lg:block lg:w-48 lg:shadow-none">
    {{-- Brand --}}
    <div class="flex items-center justify-between px-3 py-3">
        <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 flex-col items-start gap-1 px-3 font-semibold tracking-tight" data-tour="sidebar-brand">
            @if($siteLogo)
                <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-10 max-w-[168px] w-auto object-contain">
            @else
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white text-xs font-bold text-[#082b49]">AP</span>
            @endif
            <span class="text-base font-semibold tracking-tight text-white/90">Admin Panel</span>
        </a>
        <button type="button" id="admin-sidebar-close" class="rounded-md p-1 text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40 lg:hidden" aria-label="Close navigation">
            <x-icon name="x" class="h-5 w-5" />
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="grid gap-1 px-3 pb-4 text-sm" data-tour="sidebar-nav">
        @php
            $navSections = [
                'Administration' => [
                    ['categories', 'Manage Categories', 'admin.categories.index',   'admin.categories.*'],
                    ['folder',     'Media Library',    'admin.media.index',        'admin.media.*'],
                    ['users',      'Users',             'admin.users.index',        'admin.users.*'],
                    ['users',      'Early Access Signups', 'admin.early-access-leads.index', 'admin.early-access-leads.*'],
                    ['logs',       'Activity Logs',     'admin.logs.index',         'admin.logs.*'],
                    ['security',   'Security',          'admin.security',           'admin.security'],
                    ['guide',      'Admin Guide',       'admin.guide',              'admin.guide'],
                ],
            ];
        @endphp
        @php
            $navProperties = \App\Models\Property::orderBy('name')->get();
            $propertiesActive = request()->routeIs('admin.properties.*') || request()->routeIs('admin.instructions.*') || request()->routeIs('admin.guest-guide.*');
        @endphp

        <p class="mt-4 mb-1 px-3 text-xs font-bold uppercase tracking-widest text-slate-400">Guest Admin</p>
        <a href="{{ route('admin.dashboard') }}" data-tour="nav-dashboard" class="flex items-center gap-2.5 rounded-sm px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-white/30 {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
            <span class="grid h-5 w-5 shrink-0 place-items-center"><x-icon name="dashboard" class="h-4 w-4" /></span>
            <span class="font-medium">Dashboard</span>
        </a>
        <a href="{{ route('admin.guests.index') }}" data-tour="nav-calendar" class="flex items-center gap-2.5 rounded-sm px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-white/30 {{ request()->routeIs('admin.guests.*') ? 'bg-white/10 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
            <span class="grid h-5 w-5 shrink-0 place-items-center"><x-icon name="calendar" class="h-4 w-4" /></span>
            <span class="font-medium">Guests</span>
        </a>
        {{-- Properties: expandable --}}
        <div>
            <div class="flex items-center rounded-sm {{ $propertiesActive ? 'bg-white/10 text-white' : 'text-slate-200' }}">
                <a href="{{ route('admin.properties.index') }}" data-tour="nav-properties" class="flex flex-1 items-center gap-2.5 px-3 py-2.5 transition hover:text-white">
                    <span class="grid h-5 w-5 shrink-0 place-items-center"><x-icon name="properties" class="h-4 w-4" /></span>
                    <span class="font-medium">Properties</span>
                </a>
                <button type="button" onclick="document.getElementById('nav-properties-submenu').classList.toggle('hidden'); this.classList.toggle('rotate-90')" class="px-2 py-2.5 transition hover:text-white {{ $propertiesActive ? 'rotate-90' : '' }}">
                    <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                </button>
            </div>
            <div id="nav-properties-submenu" class="ml-2 grid min-w-0 gap-1 border-l border-white/10 pl-2 {{ $propertiesActive ? '' : 'hidden' }}">
                @foreach($navProperties as $navProperty)
                    @php
                        $thisPropertyActive = request()->routeIs('admin.instructions.*') || request()->routeIs('admin.guest-guide.*') || request()->routeIs('admin.properties.availability.*');
                        $thisPropertyActive = $thisPropertyActive && (request()->route('property')?->id === $navProperty->id);
                    @endphp
                    <div>
                        <button type="button" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.property-chevron').classList.toggle('rotate-90')" class="flex w-full items-start gap-1 rounded-sm px-2 py-2 text-left text-xs font-semibold transition hover:bg-white/10 {{ $thisPropertyActive ? 'bg-white/10 text-white' : 'text-slate-300 hover:text-white' }}">
                            <span class="min-w-0 flex-1 break-words leading-snug">{{ $navProperty->name }}</span>
                            <x-icon name="chevron-right" class="property-chevron mt-0.5 h-3 w-3 shrink-0 transition-transform {{ $thisPropertyActive ? 'rotate-90' : '' }}" />
                        </button>
                        <div class="ml-1 grid min-w-0 gap-1 {{ $thisPropertyActive ? '' : 'hidden' }}">
                            <a href="{{ route('admin.instructions.show', $navProperty) }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.instructions.*') && request()->route('property')?->id === $navProperty->id ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Check In/Out Details</a>
                            <a href="{{ route('admin.guest-guide.show', $navProperty) }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.guest-guide.*') && request()->route('property')?->id === $navProperty->id ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Guest Guide</a>
                            <a href="{{ route('admin.properties.availability.index', $navProperty) }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.properties.availability.*') && request()->route('property')?->id === $navProperty->id ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Availability</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $settingsActive = request()->routeIs('admin.settings.*') || request()->routeIs('admin.payments.*') || request()->routeIs('admin.notices.*');
        @endphp
        <p class="mt-4 mb-1 px-3 text-xs font-bold uppercase tracking-widest text-slate-400">Settings</p>
        <div>
            <div class="flex items-center rounded-sm {{ $settingsActive ? 'bg-white/10 text-white' : 'text-slate-200' }}">
                <a href="{{ route('admin.settings.edit') }}" data-tour="nav-settings" class="flex flex-1 items-center gap-2.5 px-3 py-2.5 transition hover:text-white">
                    <span class="grid h-5 w-5 shrink-0 place-items-center"><x-icon name="settings" class="h-4 w-4" /></span>
                    <span class="font-medium">Settings</span>
                </a>
                <button type="button" onclick="document.getElementById('nav-settings-submenu').classList.toggle('hidden'); this.classList.toggle('rotate-90')" class="px-2 py-2.5 transition hover:text-white {{ $settingsActive ? 'rotate-90' : '' }}">
                    <x-icon name="chevron-right" class="h-3.5 w-3.5" />
                </button>
            </div>
            <div id="nav-settings-submenu" class="ml-2 grid min-w-0 gap-1 border-l border-white/10 pl-2 {{ $settingsActive ? '' : 'hidden' }}">
                <a href="{{ route('admin.settings.edit') }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.settings.edit') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">General</a>
                <a href="{{ route('admin.settings.legal.edit') }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.settings.legal.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Legal</a>
                <a href="{{ route('admin.settings.notifications.edit') }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.settings.notifications.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Notifications</a>
                <a href="{{ route('admin.payments.index') }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.payments.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Payments</a>
                <a href="{{ route('admin.notices.index') }}" class="block rounded-sm px-2 py-1.5 text-xs leading-snug transition {{ request()->routeIs('admin.notices.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">Guest Notices</a>
            </div>
        </div>

        @foreach($navSections as $sectionLabel => $navItems)
            <p class="mt-4 mb-1 px-3 text-xs font-bold uppercase tracking-widest text-slate-400">{{ $sectionLabel }}</p>
            @foreach($navItems as [$icon, $label, $route, $pattern])
                @php $active = request()->routeIs($pattern); @endphp
                <a href="{{ route($route) }}"
                   data-tour="nav-{{ $icon }}"
                   class="flex items-center gap-2.5 rounded-sm px-3 py-2.5 transition focus:outline-none focus:ring-2 focus:ring-white/30 {{ $active ? 'bg-white/10 text-white' : 'text-slate-200 hover:bg-white/10 hover:text-white' }}">
                    <span class="grid h-5 w-5 shrink-0 place-items-center">
                        <x-icon :name="$icon" class="h-4 w-4" />
                    </span>
                    <span class="font-medium">{{ $label }}</span>
                </a>
            @endforeach
        @endforeach

    {{-- Sidebar tip --}}
    <div class="mx-3 mt-2 rounded-md border border-white/10 bg-white/5 p-3 text-xs text-slate-200">
        <p class="font-semibold">Client-ready demo</p>
        <p class="mt-1 leading-5">Copy a guest URL from any booking to preview the full guest experience.</p>
    </div>
</aside>

{{-- ══════════════════════════════════════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════════════════════════════════════ --}}
<main class="lg:ml-48 lg:flex-1">
    {{-- Topbar --}}
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white" data-tour="topbar">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            {{-- Mobile menu --}}
            <button type="button"
                    id="admin-sidebar-open"
                    class="rounded-md border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 lg:hidden"
                    aria-label="Open navigation">
                <x-icon name="menu" class="h-4 w-4" />
            </button>

            {{-- Page title --}}
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold text-slate-950 lg:text-lg">{{ $title ?? 'Dashboard' }}</h1>
            </div>

            {{-- Global Search --}}
            <div class="relative hidden sm:block" data-tour="global-search">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input id="global-search-input"
                           type="text"
                           placeholder="Search guests, properties…"
                           autocomplete="off"
                           class="h-9 w-48 rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm text-slate-900 transition focus:w-64 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200 lg:w-52">
                </div>
                <div id="search-dropdown"
                     class="absolute right-0 top-full mt-1.5 hidden w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div id="search-results" class="max-h-72 overflow-y-auto"></div>
                </div>
            </div>

            {{-- Notification Bell --}}
            <div class="relative" data-tour="notifications">
                <button type="button"
                        id="notif-btn"
                        class="relative grid h-9 w-9 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                        aria-label="Open notifications">
                    <x-icon name="bell" class="h-4 w-4" />
                    @php
                        // Keys are derived from the actual set of underlying booking
                        // ids, so a dismissed group automatically re-appears once its
                        // contents change (a new pending ID, a new today's check-in),
                        // rather than staying silenced forever.
                        $pendingIdBookings = \App\Models\Booking::whereNull('photo_id_path')->pluck('id')->sort()->values();
                        $todayInBookings   = \App\Models\Booking::whereDate('check_in_date', now()->toDateString())->pluck('id')->sort()->values();

                        $pendingKey = 'pending_ids:' . md5($pendingIdBookings->implode(','));
                        $todayKey   = 'checkin_today:' . md5($todayInBookings->implode(','));

                        $dismissed = auth()->user()->dismissed_notification_ids ?? [];

                        $showPending = $pendingIdBookings->isNotEmpty() && ! in_array($pendingKey, $dismissed, true);
                        $showToday   = $todayInBookings->isNotEmpty() && ! in_array($todayKey, $dismissed, true);

                        // The badge counts the unread items actually listed below,
                        // so it always matches what's in the dropdown.
                        $notifCount = ($showPending ? 1 : 0) + ($showToday ? 1 : 0);
                        $visibleKeys = array_values(array_filter([$showPending ? $pendingKey : null, $showToday ? $todayKey : null]));
                    @endphp
                    @if($notifCount > 0)
                        <span id="notif-badge" class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">{{ $notifCount }}</span>
                    @endif
                </button>

                <div id="notif-panel"
                     class="absolute right-0 top-full mt-1.5 hidden w-72 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-100 p-3">
                        <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Notifications</p>
                        @if($notifCount > 0)
                            <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" data-notif-markall-form>
                                @csrf
                                @foreach($visibleKeys as $k)
                                    <input type="hidden" name="keys[]" value="{{ $k }}">
                                @endforeach
                                <button type="submit" class="text-[11px] font-semibold text-slate-500 hover:text-slate-800">Mark all as read</button>
                            </form>
                        @endif
                    </div>
                    <div id="notif-items" class="divide-y divide-slate-100">
                        @if($showPending)
                            <div class="flex items-center gap-3 p-3 hover:bg-amber-50" data-notif-item>
                                <a href="{{ route('admin.guests.index', ['status' => 'pending']) }}" class="flex flex-1 items-center gap-3">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-600"><x-icon name="upload" class="h-4 w-4" /></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $pendingIdBookings->count() }} pending photo ID{{ $pendingIdBookings->count() > 1 ? 's' : '' }}</p>
                                        <p class="text-xs text-slate-500">Guests awaiting document review</p>
                                    </div>
                                </a>
                                <form method="POST" action="{{ route('admin.notifications.dismiss') }}" data-notif-dismiss-form>
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $pendingKey }}">
                                    <button type="submit" class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-700" aria-label="Mark as read" title="Mark as read">
                                        <x-icon name="x" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </div>
                        @endif
                        @if($showToday)
                            <div class="flex items-center gap-3 p-3 hover:bg-blue-50" data-notif-item>
                                <a href="{{ route('admin.guests.index') }}" class="flex flex-1 items-center gap-3">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-blue-100 text-blue-600"><x-icon name="calendar" class="h-4 w-4" /></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $todayInBookings->count() }} check-in{{ $todayInBookings->count() > 1 ? 's' : '' }} today</p>
                                        <p class="text-xs text-slate-500">Arrivals scheduled for today</p>
                                    </div>
                                </a>
                                <form method="POST" action="{{ route('admin.notifications.dismiss') }}" data-notif-dismiss-form>
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $todayKey }}">
                                    <button type="submit" class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-700" aria-label="Mark as read" title="Mark as read">
                                        <x-icon name="x" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </div>
                        @endif
                        @if($notifCount === 0)
                            <div class="p-5 text-center text-sm text-slate-500" data-notif-empty>
                                <x-icon name="check" class="mx-auto mb-2 h-6 w-6 text-emerald-500" />
                                All caught up. Nothing pending.
                            </div>
                        @endif
                    </div>
                    <div class="border-t border-slate-100 p-2">
                        <a href="{{ route('admin.logs.index') }}"
                           class="block rounded-md px-3 py-2 text-center text-xs font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                            View activity logs →
                        </a>
                    </div>
                </div>
            </div>

            {{-- Add Guest shortcut --}}
            <a href="{{ route('admin.guests.create') }}" class="hidden btn-primary sm:inline-flex" data-tour="add-guest-btn">
                Add Guest
            </a>

            {{-- User menu --}}
            <div class="relative" data-tour="user-menu">
                <button type="button"
                        id="user-menu-btn"
                        class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300"
                        aria-label="Open user menu">
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-[#082b49] text-xs font-bold text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                    <span class="hidden font-medium lg:block">{{ auth()->user()->name }}</span>
                </button>

                <div id="user-menu-panel"
                     class="absolute right-0 top-full mt-1.5 hidden w-52 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->roleLabel() }}</p>
                    </div>
                    <div class="p-1">
                        <a href="{{ route('admin.security') }}"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <x-icon name="security" class="h-4 w-4 text-slate-400" />
                            Security &amp; 2FA
                        </a>
                        @if(auth()->user()->canManageUsers())
                            <a href="{{ route('admin.users.index') }}"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <x-icon name="users" class="h-4 w-4 text-slate-400" />
                                Manage Team
                            </a>
                        @endif
                        <form method="post" action="{{ route('admin.tour.restart') }}">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <x-icon name="refresh" class="h-4 w-4 text-slate-400" />
                                Restart Onboarding Tour
                            </button>
                        </form>
                        <a href="{{ route('admin.guide') }}"
                           class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <x-icon name="guide" class="h-4 w-4 text-slate-400" />
                            Admin Guide
                        </a>
                    </div>
                    <div class="border-t border-slate-100 p-1">
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                                <x-icon name="x" class="h-4 w-4" />
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Alerts --}}
    <div class="page-shell pt-4 pb-0">
        @if(session('success'))
            <div id="success-flash-banner" class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 transition-opacity duration-300">
                <x-icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" />
                {{ session('success') }}
            </div>
            <script>
                setTimeout(() => {
                    const banner = document.getElementById('success-flash-banner');
                    if (!banner) return;
                    banner.style.opacity = '0';
                    setTimeout(() => banner.remove(), 300);
                }, 5000);
            </script>
        @endif
        @if(session('success') === 'Guest booking created successfully.')
            <div data-scroll-to="#guest-link-card" data-expand-communication="true"></div>
        @endif
        @if(session('error'))
            <div class="mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                <x-icon name="x" class="h-4 w-4 shrink-0 text-red-500" />
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                <div>{{ $errors->first() }}</div>
            </div>
        @endif
    </div>

    {{-- Page Content --}}
    <div class="page-shell pt-4">
        <div data-animate>
            {{ $slot }}
        </div>
    </div>
</main>

{{-- ══════════════════════════════════════════════════════════════════════════
     TOAST CONTAINER
══════════════════════════════════════════════════════════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════════════════════
     GLOBAL MEDIA PICKER (used by every shared media image field)
══════════════════════════════════════════════════════════════════════════ --}}
<div id="global-media-picker-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/40 p-4">
    <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p id="global-media-picker-breadcrumb" class="text-sm font-bold text-slate-700">Library</p>
            <div class="flex items-center gap-2">
                <label class="btn-secondary cursor-pointer text-xs">
                    Upload
                    <input type="file" id="global-media-picker-upload-input" accept="image/*" class="sr-only">
                </label>
                <button type="button" onclick="closeGlobalMediaPicker()" class="text-slate-400 hover:text-slate-700">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>
        </div>
        <div id="global-media-picker-body" class="grid max-h-96 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4"></div>
    </div>
</div>

<script>
let __globalMediaTarget = { preview: null, hidden: null, file: null };
let __globalMediaFolderId = null;

function openMediaLibraryPicker(previewId, hiddenId, fileId) {
    __globalMediaTarget = { preview: previewId, hidden: hiddenId, file: fileId };
    document.getElementById('global-media-picker-modal').classList.remove('hidden');
    document.getElementById('global-media-picker-modal').classList.add('flex');
    loadGlobalMediaPicker(null);
}

function closeGlobalMediaPicker() {
    document.getElementById('global-media-picker-modal').classList.add('hidden');
    document.getElementById('global-media-picker-modal').classList.remove('flex');
}

function loadGlobalMediaPicker(folderId) {
    __globalMediaFolderId = folderId;
    const url = '{{ route("admin.media.picker") }}' + (folderId ? '?folder_id=' + folderId : '');
    fetch(url).then(r => r.json()).then(data => {
        const body = document.getElementById('global-media-picker-body');
        const crumbText = data.breadcrumb.length ? data.breadcrumb.map(c => c.name).join(' / ') : 'Library';
        document.getElementById('global-media-picker-breadcrumb').textContent = crumbText;
        body.innerHTML = '';

        if (folderId !== null) {
            const up = document.createElement('button');
            up.type = 'button';
            up.className = 'col-span-full text-left text-xs font-semibold text-blue-600 hover:underline';
            up.textContent = '← Back';
            const parentId = data.breadcrumb.length > 1 ? data.breadcrumb[data.breadcrumb.length - 2].id : null;
            up.onclick = () => loadGlobalMediaPicker(parentId);
            body.appendChild(up);
        }

        data.folders.forEach(folder => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'flex flex-col items-center gap-1 rounded-lg border border-slate-200 p-3 hover:bg-slate-50';
            el.innerHTML = '<span class="text-2xl">📁</span><span class="truncate text-xs font-semibold">' + folder.name + '</span>';
            el.onclick = () => loadGlobalMediaPicker(folder.id);
            body.appendChild(el);
        });

        data.files.forEach(file => {
            const el = document.createElement('button');
            el.type = 'button';
            el.className = 'overflow-hidden rounded-lg border border-slate-200 bg-slate-50 hover:ring-2 hover:ring-blue-400';
            el.innerHTML = '<img src="' + file.url + '" class="h-20 w-full object-contain p-1">';
            el.onclick = () => selectGlobalMediaFile(file);
            body.appendChild(el);
        });

        if (!data.folders.length && !data.files.length) {
            body.innerHTML += '<p class="col-span-full text-center text-sm text-slate-400">No images in this folder yet.</p>';
        }
    });
}

function selectGlobalMediaFile(file) {
    if (__globalMediaTarget.preview) {
        const preview = document.getElementById(__globalMediaTarget.preview);
        preview.src = file.url;
        preview.classList.remove('hidden');
    }
    if (__globalMediaTarget.hidden) {
        document.getElementById(__globalMediaTarget.hidden).value = file.path;
    }
    if (__globalMediaTarget.file) {
        document.getElementById(__globalMediaTarget.file).value = '';
    }
    closeGlobalMediaPicker();
}

document.getElementById('global-media-picker-upload-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    if (__globalMediaFolderId) formData.append('media_folder_id', __globalMediaFolderId);
    fetch('{{ route("admin.media.files.store") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: formData,
    }).then(r => r.json()).then(data => {
        loadGlobalMediaPicker(__globalMediaFolderId);
        e.target.value = '';
        if (data && data.file) {
            selectGlobalMediaFile(data.file);
        }
    });
});

// Auto-preview newly chosen local files for any field using the shared component
document.addEventListener('change', function(e) {
    if (!e.target.matches('.media-field-upload-input')) return;
    const file = e.target.files[0];
    if (!file) return;
    const previewId = e.target.dataset.previewTarget;
    const hiddenId = e.target.dataset.hiddenTarget;
    const preview = document.getElementById(previewId);
    if (preview) {
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
    }
    if (hiddenId) {
        document.getElementById(hiddenId).value = '';
    }
});
</script>

<div id="toast-container" class="pointer-events-none fixed right-4 top-20 z-[99999] flex flex-col gap-2"></div>

{{-- ══════════════════════════════════════════════════════════════════════════
     SITE CONFIRM MODAL  (replaces window.confirm() with our own popup)
══════════════════════════════════════════════════════════════════════════ --}}
<div id="site-confirm-modal" class="fixed inset-0 z-[100000] hidden items-center justify-center bg-slate-950/50 backdrop-blur-sm p-4">
    <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl">
        <h3 id="site-confirm-title" class="text-base font-semibold text-slate-900">Are you sure?</h3>
        <p id="site-confirm-body" class="mt-2 whitespace-pre-line text-sm text-slate-600"></p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" id="site-confirm-cancel" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</button>
            <button type="button" id="site-confirm-ok" class="rounded-lg bg-[#082b49] px-3 py-2 text-sm font-medium text-white transition hover:bg-[#0b3a63]">Confirm</button>
        </div>
    </div>
</div>

<script>
// Site-styled replacement for window.confirm(). Usage on any <form>:
//   <form data-confirm="Some message" data-confirm-title="Optional title">
// Submits the form only after the user clicks "Confirm" in our own modal —
// no native browser dialog involved.
(function () {
    const modal = document.getElementById('site-confirm-modal');
    const titleEl = document.getElementById('site-confirm-title');
    const bodyEl = document.getElementById('site-confirm-body');
    const okBtn = document.getElementById('site-confirm-ok');
    const cancelBtn = document.getElementById('site-confirm-cancel');
    let pendingForm = null;

    function openConfirmModal(form) {
        pendingForm = form;
        titleEl.textContent = form.dataset.confirmTitle || 'Are you sure?';
        bodyEl.textContent = form.dataset.confirm || '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Shared by every help tooltip on any page: click toggles that
    // one panel open, closes any other open panel first (so only one
    // shows at a time), and clicking anywhere outside closes it.
    function toggleHelp(btn) {
        var wrapper = btn.closest('[data-help-wrapper]');
        var panel = wrapper ? wrapper.querySelector('[data-help-panel]') : null;
        if (!panel) return;
        var willShow = panel.classList.contains('hidden');
        document.querySelectorAll('[data-help-panel]').forEach(function (p) {
            if (p !== panel) p.classList.add('hidden');
        });
        panel.classList.toggle('hidden', !willShow);
    }
    // The help component triggers this via an inline
    // onclick="toggleHelp(this)". Inline handlers run in the global scope,
    // so this function must be exposed on window -- otherwise the click
    // throws "toggleHelp is not defined" and the ? appears to do nothing,
    // even though the function (and the click-outside listener below)
    // exist fine inside this IIFE.
    window.toggleHelp = toggleHelp;
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-help-wrapper]')) return;
        document.querySelectorAll('[data-help-panel]').forEach(function (p) {
            p.classList.add('hidden');
        });
    });

    function closeConfirmModal() {
        // A separate script (form loading-state handler) disables the
        // submit button and swaps in a spinner on every form 'submit'
        // event -- and since that listener is attached directly on the
        // form (fires before this document-level one, per event bubble
        // order), it already ran and mutated the button by the time we
        // call preventDefault() below. Cancelling here must undo that or
        // the button is left stuck showing "Working..." forever with
        // nothing to reset it, since the form never actually submitted.
        if (pendingForm) {
            var btn = pendingForm.querySelector('[type="submit"], button:not([type="button"])');
            if (btn && btn.dataset.loadingActive === 'true') {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml || btn.innerHTML;
                delete btn.dataset.loadingActive;
                delete btn.dataset.originalHtml;
            }
        }
        pendingForm = null;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.dataset.confirm) return;
        if (form.dataset.confirmed === 'true') return; // already confirmed via modal, let it through
        e.preventDefault();
        openConfirmModal(form);
    });

    okBtn.addEventListener('click', function () {
        const form = pendingForm;
        closeConfirmModal();
        if (form) {
            form.dataset.confirmed = 'true';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });

    cancelBtn.addEventListener('click', closeConfirmModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeConfirmModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeConfirmModal();
    });
})();
</script>

{{-- ══════════════════════════════════════════════════════════════════════════
     SPOTLIGHT TOUR  (shown only on first visit)
══════════════════════════════════════════════════════════════════════════ --}}
@if(auth()->check() && ! auth()->user()->admin_tour_completed_at)
    @php
        $tourSteps = [
            ['target' => null,               'title' => 'Welcome to your command centre', 'body' => 'This platform manages your property guest check-ins with professional tools. This short tour highlights each key module. Use the arrow keys or buttons to navigate.'],
            ['target' => 'sidebar-brand',    'title' => 'Your brand identity',            'body' => 'Upload your logo in Settings. It appears here in the sidebar and on all guest-facing pages for a fully branded experience.'],
            ['target' => 'sidebar-nav',      'title' => 'Main navigation',                'body' => 'Every module lives here: Properties, Guests, Categories, Content, Team, Logs, Settings, and Security. You are always one click away.'],
            ['target' => 'nav-properties',   'title' => 'Properties',                     'body' => 'Add your rental properties with address, GPS coordinates, check-in instructions, parking notes, and hero images.'],
            ['target' => 'nav-guests',       'title' => 'Guest Bookings',                 'body' => 'Create bookings to generate a secure guest URL. Each guest self-checks in via photo ID upload and GPS verification.'],
            ['target' => 'nav-categories',   'title' => 'Guide Categories',               'body' => 'Customise the welcome guide your guests see after check-in: WiFi, parking, restaurants, checkout, contacts, and more.'],
            ['target' => 'nav-users',        'title' => 'Team Management',               'body' => 'Invite team members as Manager, Staff, or Viewer. Each role has carefully scoped permissions to keep your data safe.'],
            ['target' => 'nav-logs',         'title' => 'Activity Logs',                  'body' => 'Every admin and guest action is logged here: logins, overrides, ID views, GPS events, and security alerts.'],
            ['target' => 'global-search',    'title' => 'Global Search',                  'body' => 'Search guests, properties, categories, and team members instantly from any page in the admin panel.'],
            ['target' => 'notifications',    'title' => 'Notification Bell',              'body' => 'Shows pending photo IDs, today\'s check-ins, and other important operational reminders at a glance.'],
            ['target' => 'user-menu',        'title' => 'Your profile menu',             'body' => 'Access your security settings, team management, restart this tour, and sign out from your profile button.'],
            ['target' => 'nav-settings',     'title' => 'Settings',                       'body' => 'Configure your brand, GPS radius, default guest messages, contact details, and logo. Takes less than 5 minutes to set up.'],
            ['target' => 'nav-guide',        'title' => 'Admin Guide',                    'body' => 'A built-in help section explaining every workflow, common tasks, guest flow, troubleshooting, and how to restart this tour.'],
        ];
    @endphp

    <div id="admin-onboarding-tour"
         data-steps='@json($tourSteps)'
         data-complete-url="{{ route('admin.tour.complete') }}"
         data-csrf="{{ csrf_token() }}">
    </div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════════
     MOBILE SIDEBAR OVERLAY
══════════════════════════════════════════════════════════════════════════ --}}
<div id="sidebar-overlay"
     class="fixed inset-0 z-20 hidden bg-slate-950/40 backdrop-blur-sm lg:hidden">
</div>

<script>
    const adminSidebar = document.getElementById('admin-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const sidebarOpen = document.getElementById('admin-sidebar-open');
    const sidebarClose = document.getElementById('admin-sidebar-close');

    function openAdminSidebar() {
        adminSidebar?.classList.remove('hidden');
        sidebarOverlay?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeAdminSidebar() {
        adminSidebar?.classList.add('hidden');
        sidebarOverlay?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    sidebarOpen?.addEventListener('click', openAdminSidebar);
    sidebarClose?.addEventListener('click', closeAdminSidebar);
    sidebarOverlay?.addEventListener('click', closeAdminSidebar);

    // Dropdown toggles
    ['notif-btn', 'user-menu-btn'].forEach(id => {
        const btn = document.getElementById(id);
        if (!btn) return;
        const panel = document.getElementById(id.replace('-btn', '-panel'));
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            panel.classList.toggle('hidden');
        });
    });
    document.addEventListener('click', () => {
        document.getElementById('notif-panel')?.classList.add('hidden');
        document.getElementById('user-menu-panel')?.classList.add('hidden');
        document.getElementById('search-dropdown')?.classList.add('hidden');
    });
</script>
</body>
</html>
