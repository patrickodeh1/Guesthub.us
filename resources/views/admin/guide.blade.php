<x-admin-layout title="Admin Guide">
    <div class="page-header">
        <div>
            <p class="eyebrow">Help &amp; Documentation</p>
            <h2 class="page-title">Admin Guide</h2>
            <p class="page-subtitle">Everything you need to run the guest portal professionally and efficiently.</p>
        </div>
        <div class="flex gap-2">
            <form method="post" action="{{ route('admin.tour.restart') }}">
                @csrf
                <button type="submit" class="btn-secondary">
                    <x-icon name="refresh" class="mr-2 h-4 w-4" />
                    Restart Tour
                </button>
            </form>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-tour="guide-quick-actions">
        @foreach([
            ['properties', 'Add Property',    route('admin.properties.create'), 'Start here: add your first rental property.'],
            ['guests',     'Add Guest',       route('admin.guests.create'),   'Create a guest and generate a guest URL.'],
            ['users',      'Add Team Member', route('admin.users.create'),      'Invite staff with role-based permissions.'],
            ['logs',       'View Logs',       route('admin.logs.index'),        'Review the full system audit trail.'],
        ] as [$icon, $label, $url, $desc])
            <a href="{{ $url }}" class="card card-pad flex items-start gap-3 transition hover:-translate-y-0.5 hover:shadow-md">
                <span class="icon-chip"><x-icon :name="$icon" class="h-5 w-5" /></span>
                <div>
                    <p class="font-semibold text-slate-950">{{ $label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $desc }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_300px]">
        <div class="grid gap-5">

            {{-- Interactive tour --}}
            <section class="card card-pad" data-tour="guide-tour-section">
                <div class="flex items-start gap-4">
                    <span class="icon-chip h-12 w-12"><x-icon name="guide" class="h-6 w-6" /></span>
                    <div class="flex-1">
                        <h3 class="section-title text-lg">Interactive guided tour</h3>
                        <p class="section-copy">The spotlight tour highlights each admin module live, explaining what each section does and how to use it. Keyboard-navigable and mobile-friendly.</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <form method="post" action="{{ route('admin.tour.restart') }}">
                                @csrf
                                <button type="submit" class="btn-primary">
                                    <x-icon name="map" class="mr-2 h-4 w-4" />
                                    Start Full System Tour
                                </button>
                            </form>
                            <a href="{{ route('admin.dashboard') }}" class="btn-secondary">Dashboard Tour</a>
                        </div>
                        <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                            <strong>Keyboard shortcuts:</strong>
                            <kbd class="ml-1 rounded border border-slate-300 bg-white px-1.5 py-0.5 text-xs">→</kbd> Next step ·
                            <kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 text-xs">←</kbd> Previous ·
                            <kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 text-xs">Esc</kbd> Close
                        </div>
                    </div>
                </div>
            </section>

            {{-- Guest flow --}}
            <section class="card card-pad">
                <h3 class="section-title">Complete guest flow</h3>
                <p class="section-copy">How a guest moves from guest creation to checkout, step by step.</p>
                <div class="mt-5 grid gap-3">
                    @foreach([
                        ['1', 'Admin creates guest',       'You add the guest in the Guests module. A secure URL is generated with a unique private token.',                        'guests'],
                        ['2', 'Admin shares URL with guest', 'Copy the guest URL or the pre-built invitation message from the guest detail and send via email or message.',          'copy'],
                        ['3', 'Guest submits pre-arrival',   'Guest opens the URL, enters their email, and uploads a photo ID before arrival day.',                                    'upload'],
                        ['4', 'Check-in day arrives',        'On the check-in date, the GPS verification screen unlocks. Guest taps "Verify My Location".',                           'map'],
                        ['5', 'GPS verification',            'If the guest is within the configured radius, check-in is approved instantly. Admin can manually approve if GPS fails.', 'security'],
                        ['6', 'Welcome guide unlocks',       'After check-in, the guest accesses the full property guide: WiFi, parking, restaurants, checkout, and more.',            'guide'],
                        ['7', 'Checkout',                    'On checkout day, the guide transitions to a checkout view with instructions and confirmation.',                           'checkout-instructions'],
                    ] as [$step, $title, $desc, $icon])
                        <div class="flex items-start gap-4 rounded-xl border border-slate-200 p-4">
                            <div class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#102338] text-sm font-bold text-white">{{ $step }}</div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <x-icon :name="$icon" class="h-4 w-4 text-[#b08a45]" />
                                    <p class="font-semibold text-slate-950">{{ $title }}</p>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Team roles --}}
            <section class="card card-pad">
                <h3 class="section-title">Team roles &amp; permissions</h3>
                <p class="section-copy">What each role can and cannot do across the platform.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    @foreach(\App\Models\User::ROLE_LABELS as $role => $label)
                        <div class="rounded-xl border border-slate-200 p-4">
                            <span class="badge {{ match($role) { 'owner' => 'border-purple-200 bg-purple-50 text-purple-700', 'manager' => 'border-blue-200 bg-blue-50 text-blue-700', 'staff' => 'badge-id_uploaded', default => 'badge-inactive' } }}">{{ $label }}</span>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ \App\Models\User::ROLE_DESCRIPTIONS[$role] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex gap-3">
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary">
                        <x-icon name="users" class="mr-2 h-4 w-4" />Manage Team
                    </a>
                    <a href="{{ route('admin.users.create') }}" class="btn-ghost">Add Member</a>
                </div>
            </section>

            {{-- Activity logs --}}
            <section class="card card-pad">
                <h3 class="section-title">Activity logs &amp; audit trail</h3>
                <p class="section-copy">Every action is recorded. Use logs to audit admin activity, investigate security events, and track guest behaviour.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach([
                        'Admin logins and logouts',
                        'Photo ID views (security flagged)',
                        'Manual check-in overrides',
                        'Guest GPS verification attempts',
                        'User role changes',
                        'Guest created / updated',
                        'Category and content changes',
                        'Settings and branding updates',
                    ] as $event)
                        <div class="flex items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700">
                            <span class="h-2 w-2 rounded-full bg-[#b08a45]"></span> {{ $event }}
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">
                    <a href="{{ route('admin.logs.index') }}" class="btn-secondary">
                        <x-icon name="logs" class="mr-2 h-4 w-4" />View Activity Logs
                    </a>
                </div>
            </section>

            {{-- GPS & override --}}
            <section class="card card-pad">
                <h3 class="section-title">GPS verification &amp; manual override</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <x-icon name="map" class="mb-2 h-5 w-5 text-[#b08a45]" />
                        <p class="font-semibold text-slate-950">Automatic GPS</p>
                        <p class="mt-2 text-sm text-slate-600">The guest's browser requests location. If within the configured radius (default 150m), check-in is approved instantly with a success log.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <x-icon name="security" class="mb-2 h-5 w-5 text-[#b08a45]" />
                        <p class="font-semibold text-slate-950">Manual override</p>
                        <p class="mt-2 text-sm text-slate-600">If GPS fails (browser blocked, VPN, low accuracy), open the guest record and click <strong>Manual Check-In Override</strong>. This is logged as a security event with your name and IP.</p>
                    </div>
                </div>
                <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                    GPS radius is configured in <a href="{{ route('admin.settings.edit') }}" class="font-semibold underline">Settings</a>. Default is 150 metres. Increase for properties in areas with poor GPS accuracy (dense cities, high-rise buildings).
                </div>
            </section>

            {{-- Troubleshooting --}}
            <section class="card card-pad">
                <h3 class="section-title">Troubleshooting common issues</h3>
                <div class="mt-5 grid gap-4">
                    @foreach([
                        ['Guest says the link doesn\'t work',       'Verify the guest URL in the guest detail. Regenerate the token if needed; the old URL is invalidated and a new one is created. Check that the booking status is not checked_out.'],
                        ['GPS verification keeps failing',           'Confirm property GPS coordinates are set in the Properties module. Ask the guest to allow location access in their browser. Use manual override if time-sensitive.'],
                        ['Photo ID upload fails for guest',          'File must be JPG, PNG, WEBP, or PDF under 5MB. Ask the guest to compress the image or try a different browser (Safari on iOS sometimes blocks uploads).'],
                        ['Guest can\'t see the welcome guide',       'The guide only unlocks after successful GPS verification or manual check-in approval. Check the booking status; it must be "checked_in".'],
                        ['Team member can\'t access a section',      'Review their role in Team Management. Manager and Staff roles have restricted access. Only Owner accounts can access Users and Settings.'],
                        ['Activity log shows repeated failed logins','This may indicate a brute-force attempt. Review the IP address in the log and consider blocking at your hosting firewall level.'],
                    ] as [$q, $a])
                        <div class="rounded-xl border border-slate-200 p-4">
                            <p class="font-semibold text-slate-950">{{ $q }}</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $a }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Sidebar --}}
        <aside class="grid gap-4 self-start">
            <div class="lux-panel p-5">
                <p class="eyebrow">Guided tour</p>
                <p class="mt-2 text-base font-semibold text-slate-950">Need a refresher?</p>
                <p class="mt-2 text-sm text-slate-600">Restart the interactive spotlight tour to walk through every module with live highlighting and step-by-step explanations.</p>
                <form method="post" action="{{ route('admin.tour.restart') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-primary w-full">
                        <x-icon name="refresh" class="mr-2 h-4 w-4" />
                        Restart Tour
                    </button>
                </form>
            </div>

            <div class="card card-pad">
                <h4 class="section-title">Quick links</h4>
                <div class="mt-4 grid gap-2">
                    @foreach([
                        ['Properties',    route('admin.properties.index'), 'properties'],
                        ['Guests',route('admin.guests.index'),   'guests'],
                        ['Categories',    route('admin.categories.index'), 'categories'],
                        ['Team',          route('admin.users.index'),      'users'],
                        ['Activity Logs', route('admin.logs.index'),       'logs'],
                        ['Settings',      route('admin.settings.edit'),    'settings'],
                        ['Security',      route('admin.security'),         'security'],
                    ] as [$label, $url, $icon])
                        <a href="{{ $url }}" class="flex items-center gap-2.5 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            <x-icon :name="$icon" class="h-4 w-4 text-slate-400" />
                            {{ $label }}
                            <x-icon name="arrow-right" class="ml-auto h-3 w-3 text-slate-400" />
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card card-pad">
                <h4 class="section-title">Go-live checklist</h4>
                <div class="mt-4 grid gap-2 text-sm">
                    @foreach([
                        'Property with GPS coordinates added',
                        'Brand logo uploaded in Settings',
                        'Contact details configured',
                        'Guide categories added',
                        'First guest created',
                        'Guest URL tested end-to-end',
                        '2FA enabled on owner account',
                        'Team members invited (if any)',
                    ] as $item)
                        <div class="flex items-start gap-2 rounded-lg border border-slate-100 px-3 py-2">
                            <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#b08a45]" />
                            <span class="text-slate-700">{{ $item }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
</x-admin-layout>
