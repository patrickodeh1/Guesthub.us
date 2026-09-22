<x-admin-layout title="Team Management">
    <div class="page-header" data-tour="users-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h2 class="page-title">Team Management</h2>
            <p class="page-subtitle">Manage admin users and control their access roles and permissions.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn-primary" data-tour="add-user-btn">
            <x-icon name="plus" class="mr-2 h-4 w-4" />
            Add Team Member
        </a>
    </div>

    {{-- Filters --}}
    <form method="get" class="card card-pad mb-5" data-tour="users-filters">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1" style="min-width:180px">
                <label class="field-label">Search</label>
                <div class="relative mt-2">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input name="search" value="{{ request('search') }}" type="text" placeholder="Name or email…"
                           class="input pl-9">
                </div>
            </div>
            <div>
                <label class="field-label">Role</label>
                <select name="role" class="input mt-2">
                    <option value="">All roles</option>
                    @foreach(\App\Models\User::ROLE_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected(request('role') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="field-label">Status</label>
                <select name="status" class="input mt-2">
                    <option value="">All</option>
                    <option value="active"   @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn-primary">Filter</button>
            @if(request()->hasAny(['search', 'role', 'status']))
                <a href="{{ route('admin.users.index') }}" class="btn-secondary">Clear</a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="table-wrap" data-tour="users-table">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Team Member</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="group">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#102338] text-xs font-bold text-white">
                                    {{ $user->initials() }}
                                </span>
                                <div>
                                    <a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-slate-950 hover:text-[#102338]">
                                        {{ $user->name }}
                                        @if($user->id === auth()->id())
                                            <span class="ml-1.5 badge badge-active">You</span>
                                        @endif
                                    </a>
                                    <div class="text-sm text-slate-500">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ match($user->role) { 'owner' => 'border-purple-200 bg-purple-50 text-purple-700', 'manager' => 'border-blue-200 bg-blue-50 text-blue-700', 'staff' => 'badge-id_uploaded', default => 'badge-inactive' } }}">
                                {{ $user->roleLabel() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $user->isActive() ? 'badge-active' : 'badge-inactive' }}">
                                {{ $user->isActive() ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-sm text-slate-600">
                            @if($user->last_login_at)
                                <span title="{{ $user->last_login_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y H:i') }}">
                                    {{ $user->last_login_at->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-slate-400">Never</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-500">{{ $user->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y') }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn-secondary px-3 py-1.5 text-xs">View</a>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary px-3 py-1.5 text-xs">Edit</a>
                                @if($user->id !== auth()->id())
                                    <form method="post" action="{{ route('admin.users.toggle-status', $user) }}">
                                        @csrf
                                        <button class="btn-secondary px-3 py-1.5 text-xs {{ $user->isActive() ? 'text-amber-700' : 'text-emerald-700' }}">
                                            {{ $user->isActive() ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-16 text-center">
                            <span class="icon-chip mx-auto mb-4 h-14 w-14"><x-icon name="users" class="h-7 w-7" /></span>
                            <p class="text-base font-semibold text-slate-950">No team members yet</p>
                            <p class="mt-1 text-sm text-slate-500">Invite your first team member to get started.</p>
                            <a href="{{ route('admin.users.create') }}" class="btn-primary mt-4">Add Team Member</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div class="mt-4">{{ $users->links() }}</div>
    @endif

    {{-- Role reference --}}
    <div class="mt-6 card card-pad" data-tour="roles-overview">
        <h3 class="section-title">Role permissions overview</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(\App\Models\User::ROLE_LABELS as $role => $label)
                <div class="rounded-xl border border-slate-200 p-4">
                    <span class="badge {{ match($role) { 'owner' => 'border-purple-200 bg-purple-50 text-purple-700', 'manager' => 'border-blue-200 bg-blue-50 text-blue-700', 'staff' => 'badge-id_uploaded', default => 'badge-inactive' } }}">{{ $label }}</span>
                    <p class="mt-3 text-xs leading-5 text-slate-600">{{ \App\Models\User::ROLE_DESCRIPTIONS[$role] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
