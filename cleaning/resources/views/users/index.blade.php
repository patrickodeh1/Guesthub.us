<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-gray-200 leading-tight">
                    Users Management
                </h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Manage users, roles, and permissions.
                </p>
            </div>
            @role('admin|owner|company')
                <x-button href="{{ route('users.create') }}" class="bg-indigo-600 hover:bg-indigo-700 w-full sm:w-auto whitespace-nowrap">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create User
                </x-button>
            @endrole
        </div>
    </x-slot>

    {{-- Success Message --}}
    <x-flash.ok :message="session('success')" />

    {{-- Filters --}}
    <x-card class="mb-6">
        <form method="get" class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-end gap-3 px-1 sm:px-0">
            <div class="flex-1 min-w-0 sm:min-w-[180px]">
                <x-form.label value="Filter by Role" />
                <x-form.select name="role" class="w-full max-w-full">
                    <option value="">All Roles</option>
                    <option value="admin" @selected(request('role') === 'admin')>Administrator</option>
                    <option value="owner" @selected(request('role') === 'owner')>Owner</option>
                    <option value="housekeeper" @selected(request('role') === 'housekeeper')>Housekeeper</option>
                </x-form.select>
            </div>
            <div class="flex-1 min-w-0 sm:min-w-[150px]">
                <x-form.label value="Filter by Status" />
                <x-form.select name="status" class="w-full max-w-full">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Terminated</option>
                </x-form.select>
            </div>
            <div class="flex-1 min-w-0 sm:min-w-[220px]">
                <x-form.label value="Search" />
                <x-form.input name="q" type="text" class="w-full max-w-full" :value="old('q', request('q'))"
                    placeholder="Search by name or email..." />
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <x-button type="submit" variant="secondary" class="w-full sm:w-auto whitespace-nowrap">Apply Filters</x-button>
                <x-button :href="route('users.index')" variant="secondary" class="w-full sm:w-auto whitespace-nowrap">Clear</x-button>
            </div>
        </form>
    </x-card>

    {{-- Users List --}}
    <x-card>
        @if ($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Contact
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Role & Status
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors {{ !$user->is_active ? 'bg-red-50/20 dark:bg-red-950/10' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <img class="h-12 w-12 rounded-full object-cover ring-2 {{ $user->is_active ? 'ring-gray-200 dark:ring-gray-700' : 'ring-red-300 dark:ring-red-800' }}"
                                                src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                {{ $user->name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                ID: {{ $user->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $user->email }}</div>
                                    @if ($user->phone_number)
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            {{ $user->phone_number }}
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-400 italic">No phone number</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1.5 items-start">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($user->roles as $role)
                                                @php
                                                    $roleColors = [
                                                        'admin' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                        'owner' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                        'company' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
                                                        'housekeeper' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    ];
                                                    $color = $roleColors[$role->name] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                                @endphp
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $color }}">
                                                    {{ ucfirst($role->name) }}
                                                </span>
                                            @endforeach
                                            @if ($user->roles->isEmpty())
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                    No role
                                                </span>
                                            @endif
                                        </div>
                                        <div>
                                            @if ($user->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500"></span> Active
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300">
                                                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-red-500"></span> Terminated
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Admin & Authorized Owner Termination Reason Details --}}
                                        @php
                                            $authUser = auth()->user();
                                            $canViewReason = false;
                                            if ($authUser) {
                                                if ($authUser->hasRole('admin')) {
                                                    $canViewReason = true;
                                                } elseif ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && $user->hasRole('housekeeper')) {
                                                    $canViewReason = true;
                                                }
                                            }
                                        @endphp
                                        @if ($canViewReason)
                                            @if (! $user->is_active && $user->termination_reason)
                                                <div class="mt-1 text-xs text-red-600 dark:text-red-400 italic max-w-xs truncate" title="{{ $user->termination_reason }}">
                                                    Reason: "{{ $user->termination_reason }}"
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Edit Button --}}
                                        <x-button variant="secondary" href="{{ route('users.edit', $user) }}"
                                            class="!py-1.5 !px-3 text-xs">
                                            <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </x-button>

                                        {{-- Admin & Authorized Owner Termination & Reactivation Controls --}}
                                        @php
                                            $canManageStatus = false;
                                            if ($authUser && $authUser->id !== $user->id) {
                                                if ($authUser->hasRole('admin') && !$user->hasRole('admin')) {
                                                    $canManageStatus = true;
                                                } elseif ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && $user->hasRole('housekeeper')) {
                                                    $canManageStatus = true;
                                                }
                                            }
                                        @endphp
                                        @if ($canManageStatus)
                                            @if ($user->is_active)
                                                    {{-- Deactivate Button & Modal --}}
                                                    <div x-data="{ openDeactivateModal: false }">
                                                        <x-button type="button" variant="secondary" @click="openDeactivateModal = true"
                                                            class="!py-1.5 !px-3 text-xs !bg-amber-600 hover:!bg-amber-700 !text-white">
                                                            <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                            </svg>
                                                            Deactivate
                                                        </x-button>

                                                        <div x-show="openDeactivateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                                                            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                                                <div x-show="openDeactivateModal" @click="openDeactivateModal = false" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"></div>

                                                                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                                                                <div x-show="openDeactivateModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6 border border-gray-100 dark:border-gray-700">
                                                                    <form method="post" action="{{ route('users.deactivate', $user) }}">
                                                                        @csrf
                                                                        <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400 mb-3">
                                                                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                                            </svg>
                                                                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Deactivate Cleaner Account</h3>
                                                                        </div>
                                                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                                                            Are you sure you want to deactivate <strong>{{ $user->name }}</strong>? This cleaner will be blocked from logging in and removed from all future assignment dropdowns. All historical cleaning records and reports will be preserved.
                                                                        </p>

                                                                        <div class="mb-5">
                                                                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                                                                Reason for Termination <span class="text-red-500">*</span>
                                                                            </label>
                                                                            <textarea name="termination_reason" required rows="3"
                                                                                class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm focus:ring-indigo-500 focus:border-indigo-500 p-3"
                                                                                placeholder="Specify the reason for deactivation (e.g. Left company, performance, policy breach)..."></textarea>
                                                                        </div>

                                                                        <div class="flex justify-end gap-3">
                                                                            <x-button type="button" variant="secondary" @click="openDeactivateModal = false">Cancel</x-button>
                                                                            <x-button type="submit" class="!bg-red-600 hover:!bg-red-700 !text-white">Confirm Deactivation</x-button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    {{-- Reactivate Button --}}
                                                    <form method="post" action="{{ route('users.reactivate', $user) }}" class="inline" onsubmit="return confirm('Are you sure you want to reactivate {{ $user->name }}?');">
                                                        @csrf
                                                        <x-button type="submit" variant="secondary" class="!py-1.5 !px-3 text-xs !bg-emerald-600 hover:!bg-emerald-700 !text-white">
                                                            <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                            Reactivate
                                                        </x-button>
                                                    </form>
                                                @endif
                                            @endif

                                        {{-- Delete Button (admin only, not for self) --}}
                                        @role('admin')
                                            @if (auth()->id() !== $user->id)
                                                <form method="post" action="{{ route('users.destroy', $user) }}"
                                                    class="inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button type="submit" variant="secondary"
                                                        class="!py-1.5 !px-3 text-xs !bg-red-600 hover:!bg-red-700 !text-white" title="Permanent Delete">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </x-button>
                                                </form>
                                            @endif
                                        @endrole
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No users found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if (request()->filled('role') || request()->filled('q'))
                        Try adjusting your filters.
                    @else
                        Get started by creating a new user.
                    @endif
                </p>
                @role('admin|owner|company')
                    <div class="mt-6">
                        <x-button href="{{ route('users.create') }}" class="bg-indigo-600 hover:bg-indigo-700">
                            Create User
                        </x-button>
                    </div>
                @endrole
            </div>
        @endif
    </x-card>
</x-app-layout>
