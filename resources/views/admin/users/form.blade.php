@php
    $editing = $user->exists;
@endphp
<x-admin-layout :title="$editing ? 'Edit '.$user->name : 'Add Team Member'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Team Management</p>
            <h2 class="page-title">{{ $editing ? 'Edit '.$user->name : 'Add Team Member' }}</h2>
            <p class="page-subtitle">{{ $editing ? 'Update account details and role permissions.' : 'Create a new admin user account with role-based permissions.' }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn-secondary">← Back to Team</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <form method="post"
              action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
              class="grid gap-5">
            @csrf
            @if($editing) @method('PUT') @endif

            {{-- Profile --}}
            <div class="card card-pad">
                <h3 class="section-title">Profile information</h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="name">Full name <span class="text-red-500">*</span></label>
                        <input id="name" name="name" type="text" required
                               value="{{ old('name', $user->name) }}"
                               class="input" placeholder="Jane Smith">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="email">Email address <span class="text-red-500">*</span></label>
                        <input id="email" name="email" type="email" required
                               value="{{ old('email', $user->email) }}"
                               class="input" placeholder="jane@example.com">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="phone">Phone number</label>
                        <input id="phone" name="phone" type="tel"
                               value="{{ old('phone', $user->phone) }}"
                               class="input" placeholder="+1 555 000 0000">
                        @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="role">Role <span class="text-red-500">*</span></label>
                        <select id="role" name="role" required class="input">
                            @foreach(\App\Models\User::ROLE_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected(old('role', $user->role) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-4">
                    <label class="field-label" for="host_name">Host / business name</label>
                    <input id="host_name" name="host_name" type="text"
                           value="{{ old('host_name', $user->host_name) }}"
                           class="input" placeholder="e.g. Seaside Stays LLC">
                    <p class="mt-1 text-xs text-slate-500">The host or business responsible for the listings. Shown as the host party on guest agreements.</p>
                    @error('host_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="mt-4">
                    <label class="field-label" for="notes">Internal notes</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="textarea" placeholder="Optional notes about this team member…">{{ old('notes', $user->notes) }}</textarea>
                    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Password --}}
            <div class="card card-pad">
                <h3 class="section-title">{{ $editing ? 'Change password' : 'Set password' }}</h3>
                @if($editing)
                    <p class="section-copy">Leave blank to keep the current password.</p>
                @endif
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="password">{{ $editing ? 'New password' : 'Password' }} @if(!$editing)<span class="text-red-500">*</span>@endif</label>
                        <input id="password" name="password" type="password"
                               {{ ! $editing ? 'required' : '' }}
                               class="input" placeholder="Min. 8 characters"
                               autocomplete="new-password">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="field-label" for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               class="input" placeholder="Repeat password"
                               autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" data-loading-text="{{ $editing ? 'Saving…' : 'Creating account…' }}">
                    {{ $editing ? 'Save changes' : 'Create account' }}
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn-ghost">Cancel</a>
                @if($editing && $user->id !== auth()->id() && ! $user->isOwner())
                    <form method="post" action="{{ route('admin.users.destroy', $user) }}" class="ml-auto"
                          onsubmit="return confirm('Delete this account permanently? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-danger">Delete account</button>
                    </form>
                @endif
            </div>
        </form>

        {{-- Sidebar info --}}
        <aside class="grid gap-4 self-start">
            {{-- Role descriptions --}}
            <div class="card card-pad">
                <h3 class="section-title">Role permissions</h3>
                <div class="mt-4 grid gap-3">
                    @foreach(\App\Models\User::ROLE_LABELS as $role => $label)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <span class="badge mb-2 {{ match($role) { 'owner' => 'border-purple-200 bg-purple-50 text-purple-700', 'manager' => 'border-blue-200 bg-blue-50 text-blue-700', 'staff' => 'badge-id_uploaded', default => 'badge-inactive' } }}">{{ $label }}</span>
                            <p class="text-xs leading-5 text-slate-600">{{ \App\Models\User::ROLE_DESCRIPTIONS[$role] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($editing)
                <div class="card card-pad">
                    <h3 class="section-title">Account info</h3>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                            <dd class="mt-1"><span class="badge {{ $user->isActive() ? 'badge-active' : 'badge-inactive' }}">{{ $user->isActive() ? 'Active' : 'Inactive' }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last login</dt>
                            <dd class="mt-1 text-slate-700">{{ $user->last_login_at?->copy()->setTimezone(config('app.display_timezone'))->format('d M Y H:i') ?? 'Never' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Member since</dt>
                            <dd class="mt-1 text-slate-700">{{ $user->created_at->copy()->setTimezone(config('app.display_timezone'))->format('d M Y') }}</dd>
                        </div>
                    </dl>
                    @if($user->id !== auth()->id())
                        <form method="post" action="{{ route('admin.users.toggle-status', $user) }}" class="mt-4">
                            @csrf
                            <button type="submit"
                                    class="w-full {{ $user->isActive() ? 'btn-danger' : 'btn-accent' }}">
                                {{ $user->isActive() ? 'Deactivate account' : 'Activate account' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </aside>
    </div>
</x-admin-layout>
