<x-perfect-scrollbar as="nav" aria-label="main" class="flex flex-col flex-1 gap-4 px-3">

    {{-- Primary --}}
    <x-sidebar.link title="Command Center"
        href="{{ route('dashboard') }}"
        :isActive="request()->routeIs('dashboard') || request()->routeIs('welcome')">
        <x-slot name="icon"><x-icons.dashboard class="w-6 h-6" aria-hidden="true" /></x-slot>
    </x-sidebar.link>

    {{-- Scheduling (All authenticated users) --}}
    @auth
        <x-sidebar.dropdown title="Scheduling"
            :active="request()->routeIs('manage.sessions.*') || request()->routeIs('sessions.*') || request()->routeIs('calendar.*') || request()->routeIs('assignments.*')">
            <x-slot name="icon"><x-icons.assignment class="w-6 h-6" /></x-slot>

            <x-sidebar.sublink title="Hub"
                href="{{ route('calendar.index') }}"
                :active="request()->routeIs('calendar.index')" />

            <x-sidebar.sublink title="Jobs"
                href="{{ route('manage.sessions.index') }}"
                :active="request()->routeIs('manage.sessions.index')" />

            <x-sidebar.sublink title="New Job"
                href="{{ route('manage.sessions.create') }}"
                :active="request()->routeIs('manage.sessions.create')" />

            {{-- Jump to HK list view --}}
            <x-sidebar.sublink title="My Jobs"
                href="{{ route('assignments.index') }}"
                :active="request()->routeIs('assignments.*') || request()->routeIs('sessions.*')" />
        </x-sidebar.dropdown>
    @endauth

    {{-- Standalone "My Jobs" for housekeepers without admin/owner role --}}
    @if(auth()->user()->hasRole('housekeeper') && !auth()->user()->hasAnyRole(['admin', 'owner']))
        <x-sidebar.link title="My Jobs"
            href="{{ route('assignments.index') }}"
            :isActive="request()->routeIs('assignments.*') || request()->routeIs('sessions.*')">
            <x-slot name="icon"><x-icons.assignment class="w-6 h-6" aria-hidden="true" /></x-slot>
        </x-sidebar.link>

        <x-sidebar.link title="Pre-Arrival Training"
            href="{{ route('training.index') }}"
            :isActive="request()->routeIs('training.*')">
            <x-slot name="icon">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </x-slot>
        </x-sidebar.link>
    @endif

    {{-- Properties --}}
    @role('admin|owner|company')
        <x-sidebar.dropdown title="Properties"
            :active="request()->routeIs('properties.*')">
            <x-slot name="icon"><x-heroicon-o-home class="w-6 h-6" aria-hidden="true" /></x-slot>

            <x-sidebar.sublink title="All Properties"
                href="{{ route('properties.index') }}"
                :active="request()->routeIs('properties.index')" />

            @role('admin|owner|company')
                <x-sidebar.sublink title="Add New"
                    href="{{ route('properties.create') }}"
                    :active="request()->routeIs('properties.create')" />
            @endrole
        </x-sidebar.dropdown>
    @endrole

    {{-- Resources (All authenticated users) --}}
    @auth
        <x-sidebar.dropdown title="Resources"
            :active="request()->routeIs('resources.*')">
            <x-slot name="icon">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </x-slot>

            <x-sidebar.sublink title="Photos"
                href="{{ route('resources.photos') }}"
                :active="request()->routeIs('resources.photos')" />

            <x-sidebar.sublink title="Videos"
                href="{{ route('resources.videos') }}"
                :active="request()->routeIs('resources.videos')" />

            <x-sidebar.sublink title="Guides"
                href="{{ route('resources.guides') }}"
                :active="request()->routeIs('resources.guides')" />
        </x-sidebar.dropdown>
    @endauth

    {{-- Video Library --}}
    @role('admin|owner|company')
        <x-sidebar.dropdown title="Video Library"
            :active="request()->routeIs('admin.videos.*')">
            <x-slot name="icon">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
            </x-slot>

            <x-sidebar.sublink title="All Videos"
                href="{{ route('admin.videos.index') }}"
                :active="request()->routeIs('admin.videos.index')" />

            <x-sidebar.sublink title="Upload Video"
                href="{{ route('admin.videos.create') }}"
                :active="request()->routeIs('admin.videos.create')" />
        </x-sidebar.dropdown>
    @endrole

    {{-- Users (Admin/Owner/Company) --}}
    @role('owner|admin|company')
        <x-sidebar.dropdown title="Users"
            :active="request()->routeIs('users.*')">
            <x-slot name="icon"><x-heroicon-o-users class="w-6 h-6" aria-hidden="true" /></x-slot>

            <x-sidebar.sublink title="Create User"
                href="{{ route('users.create') }}"
                :active="request()->routeIs('users.create')" />

            {{-- All (no role filter) --}}
            <x-sidebar.sublink title="All Users"
                href="{{ route('users.index') }}"
                :active="request()->routeIs('users.index') && !request()->filled('role')" />

            @role('admin')
                <x-sidebar.sublink title="Admins"
                    href="{{ route('users.index', ['role' => 'admin']) }}"
                    :active="request()->routeIs('users.index') && request('role') === 'admin'" />
            @endrole

            <x-sidebar.sublink title="Owners"
                href="{{ route('users.index', ['role' => 'owner']) }}"
                :active="request()->routeIs('users.index') && request('role') === 'owner'" />

            @role('admin')
                <x-sidebar.sublink title="Companies"
                    href="{{ route('users.index', ['role' => 'company']) }}"
                    :active="request()->routeIs('users.index') && request('role') === 'company'" />
            @endrole

            <x-sidebar.sublink title="Housekeepers"
                href="{{ route('users.index', ['role' => 'housekeeper']) }}"
                :active="request()->routeIs('users.index') && request('role') === 'housekeeper'" />
        </x-sidebar.dropdown>
    @endrole

    {{-- System / Audit --}}
    @role('admin')
        <x-sidebar.link title="Settings"
            href="{{ route('settings.index') }}"
            :isActive="request()->routeIs('settings.*')">
            <x-slot name="icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </x-slot>
        </x-sidebar.link>

{{--
        <x-sidebar.link title="Activity Log"
            href="{{ route('activity.index') }}"
            :isActive="request()->routeIs('activity.*')">
            <x-slot name="icon"><x-icons.activity class="w-6 h-6" aria-hidden="true" /></x-slot>
        </x-sidebar.link>
--}}
    @endrole

</x-perfect-scrollbar>
