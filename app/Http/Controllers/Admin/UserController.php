<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            ))
            ->when($request->role, fn ($q, $r) => $q->role($r))
            ->when(! auth()->user()->hasRole('admin'), fn ($q) => $this->scopeVisibleUsers($q, auth()->user()))
            ->when($request->status !== 'all', function ($q) use ($request) {
                $inactive = $request->status === 'inactive';
                $q->where(fn ($w) => $inactive
                    ? $w->where('status', 'inactive')
                    : $w->where('status', '<>', 'inactive')->orWhereNull('status'));
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(), 'assignableRoles' => $this->assignableRoles(auth()->user())]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', Rule::in(User::ROLES)],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['integer', 'exists:users,id'],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
            'assignments_submitted' => ['nullable', 'boolean'],
            'preferences.required_instruction_views' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ]);

        $dupEmail = strtolower(trim((string) ($data['email'] ?? '')));
        $dupPhone = trim((string) ($data['phone_number'] ?? ''));
        if ($dupEmail !== '' || $dupPhone !== '') {
            $terminatedDupe = User::where(function ($q) use ($dupEmail, $dupPhone) {
                if ($dupEmail !== '') { $q->orWhere('email', $dupEmail); }
                if ($dupPhone !== '') { $q->orWhere('phone_number', $dupPhone); }
            })->first();
            if ($terminatedDupe) {
                $field = ($dupEmail !== '' && $terminatedDupe->email === $dupEmail) ? 'email' : 'phone_number';
                $msg = ! $terminatedDupe->is_active
                    ? 'This person already exists but has been terminated. Reactivate the existing account instead of creating a new one.'
                    : ($field === 'email' ? 'The email has already been taken.' : 'The phone number has already been taken.');
                return back()->withInput()->withErrors([$field => $msg]);
            }
        }

        $authUser = auth()->user();
        abort_unless(in_array($data['role'], $this->assignableRoles($authUser), true), 403, 'You cannot create that role.');
        if (! $authUser->hasRole('admin')) {
            $data['owner_id'] = $authUser->id;
        }
        $role = $data['role'];
        unset($data['role']);
        $data['password']   = Hash::make($data['password']);
        $data['status']     = 'active';
        $data['must_change_password'] = true;
        $data['created_by'] = auth()->id();

        $propertyIds = $this->limitPropertyIds($data['property_ids'] ?? []);
        $ownerIds = $this->limitOwnerIds($data['owner_ids'] ?? []);
        unset($data['property_ids'], $data['owner_ids'], $data['assignments_submitted']);

        if (empty(array_filter($data['preferences'] ?? [], fn ($v) => $v !== null))) {
            unset($data['preferences']);
        }
        $newUser = User::create($data);
        $newUser->forceFill(['email_verified_at' => now()])->save();
        $newUser->assignRole($role);
        $newUser->properties()->sync($propertyIds);
        if (in_array($role, ['housekeeper', 'company'], true)) {
            $newUser->managedOwners()->sync($ownerIds);
        }

        ActivityLogService::admin('user_created', auth()->user()->name." created user account for {$newUser->name} ({$newUser->email}).", 'users', [
            'subject_type' => User::class,
            'subject_id'   => $newUser->id,
            'severity'     => 'success',
            'metadata'     => ['role' => $role, 'email' => $newUser->email],
        ]);

        return redirect()->route('admin.users.show', $newUser)->with('success', "User account created for {$newUser->name}.");
    }

    public function show(User $user)
    {
        $recentLogs = ActivityLog::where(fn ($q) => $q
            ->where('user_id', $user->id)
            ->orWhere(fn ($inner) => $inner->where('actor_type', 'admin')->where('actor_id', $user->id))
        )->latest()->take(20)->get();

        $this->authorizeTarget($user, true);

        $familiarities = collect();
        if ($user->hasRole('housekeeper')) {
            $familiarities = \App\Models\CleanerInstructionFamiliarity::with('task')->where('user_id', $user->id)->get();
        }
        $requiredViews = max(1, (int) ($user->preferences['required_instruction_views'] ?? 3));

        return view('admin.users.show', compact('user', 'recentLogs', 'familiarities', 'requiredViews'));
    }

    public function edit(User $user)
    {
        $this->authorizeTarget($user);

        return view('admin.users.form', ['user' => $user, 'assignableRoles' => $this->assignableRoles(auth()->user())]);
    }

    public function update(Request $request, User $user)
    {
        /** @var User $authUser */
        $authUser = auth()->user();

        if ($user->isAdmin() && ! $authUser->isAdmin()) {
            abort(403, 'Only an admin can edit another admin account.');
        }

        $this->authorizeTarget($user);

        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['required', Rule::in(User::ROLES)],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['integer', 'exists:users,id'],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
            'assignments_submitted' => ['nullable', 'boolean'],
            'preferences.required_instruction_views' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['string', 'min:8', 'confirmed'];
        }

        $data = $request->validate($rules);

        if (! $authUser->hasRole('admin')) {
            unset($data['owner_id']);
            if ($data['role'] !== $user->getRoleNames()->first()) {
                abort_if($authUser->id === $user->id, 403, 'You cannot change your own role.');
                abort_unless(in_array($data['role'], $this->assignableRoles($authUser), true), 403, 'You cannot assign that role.');
            }
        }

        $oldRole = $user->getRoleNames()->first();
        $newRole = $data['role'];
        unset($data['role']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $propertyIds = $this->limitPropertyIds($data['property_ids'] ?? []);
        $ownerIds = $this->limitOwnerIds($data['owner_ids'] ?? []);
        $assignmentsSubmitted = ! empty($data['assignments_submitted']);
        unset($data['property_ids'], $data['owner_ids'], $data['assignments_submitted']);

        if (isset($data['preferences'])) {
            $data['preferences'] = array_merge($user->preferences ?? [], array_filter($data['preferences'], fn ($v) => $v !== null));
        }
        $user->update($data);
        if ($assignmentsSubmitted) {
            $keepProps = $authUser->hasRole('admin') ? [] : $user->properties()->pluck('properties.id')->map(fn ($i) => (int) $i)->diff($this->visiblePropertyIds())->all();
            $user->properties()->sync(array_merge($keepProps, $propertyIds));
            if (in_array($newRole, ['housekeeper', 'company'], true)) {
                $keepOwners = $authUser->hasRole('admin') ? [] : $user->managedOwners()->pluck('users.id')->map(fn ($i) => (int) $i)->diff($this->visibleOwnerIds())->all();
                $user->managedOwners()->sync(array_merge($keepOwners, $ownerIds));
            }
        }
        $user->syncRoles([$newRole]);

        if ($oldRole !== $newRole) {
            ActivityLogService::security('role_changed', "{$authUser->name} changed {$user->name}'s role from {$oldRole} to {$newRole}.", [
                'subject_type' => User::class,
                'subject_id'   => $user->id,
                'metadata'     => ['old_role' => $oldRole, 'new_role' => $newRole],
            ]);
        } else {
            ActivityLogService::admin('user_updated', "{$authUser->name} updated user account for {$user->name}.", 'users', [
                'subject_type' => User::class,
                'subject_id'   => $user->id,
            ]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', "User account for {$user->name} updated.");
    }

    public function destroy(User $user)
    {
        /** @var User $authUser */
        $authUser = auth()->user();

        if ($user->id === $authUser->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin()) {
            return back()->with('error', 'Admin accounts cannot be deleted.');
        }

        abort_unless($authUser->hasRole('admin'), 403, 'Only administrators can delete users.');

        $name = $user->name;
        $user->delete();

        ActivityLogService::admin('user_deleted', "{$authUser->name} deleted user account: {$name}.", 'users', [
            'severity' => 'warning',
            'metadata' => ['deleted_name' => $name],
        ]);

        return redirect()->route('admin.users.index')->with('success', "User account for {$name} has been removed.");
    }

    private function assignableRoles(User $auth): array
    {
        if ($auth->hasRole('admin')) {
            return User::ROLES;
        }
        return $auth->hasRole('company') ? ['owner', 'housekeeper'] : ['housekeeper'];
    }

    private function scopeVisibleUsers($query, User $auth)
    {
        return $query->where(function ($q) use ($auth) {
            $q->where('users.id', $auth->id)
                ->orWhere('users.owner_id', $auth->id)
                ->orWhereExists(function ($s) use ($auth) {
                    $s->selectRaw(1)->from('housekeeper_owner as ho')
                        ->whereColumn('ho.housekeeper_id', 'users.id')->where('ho.owner_id', $auth->id);
                })
                ->orWhereExists(function ($s) use ($auth) {
                    $s->selectRaw(1)->from('cleaning_sessions as cs')
                        ->whereColumn('cs.housekeeper_id', 'users.id')->where('cs.owner_id', $auth->id);
                });
            if ($auth->hasRole('company')) {
                $q->orWhereIn('users.owner_id', fn ($s) => $s->select('id')->from('users as u2')->where('u2.owner_id', $auth->id));
            }
        });
    }

    private function authorizeTarget(User $target, bool $viewing = false): void
    {
        $auth = auth()->user();
        if ($auth->hasRole('admin')) {
            return;
        }
        abort_unless($auth->hasAnyRole(['owner', 'company']), 403);
        abort_if($target->hasRole('admin'), 403, 'You cannot access administrator accounts.');
        if ((int) $target->id === (int) $auth->id || (int) $target->owner_id === (int) $auth->id) {
            return;
        }
        if ($viewing && $this->scopeVisibleUsers(User::query(), $auth)->whereKey($target->id)->exists()) {
            return;
        }
        abort(403, 'This user is not assigned to you.');
    }

    private function visibleOwnerIds(): array
    {
        $auth = auth()->user();
        $ids = [(int) $auth->id];
        if ($auth->hasRole('company')) {
            $ids = array_merge($ids, User::where('owner_id', $auth->id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['owner', 'company']))
                ->pluck('id')->map(fn ($i) => (int) $i)->all());
        }
        return $ids;
    }

    private function visiblePropertyIds(): array
    {
        return \App\Models\Property::query()->visibleTo(auth()->user())->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    private function limitOwnerIds(array $ids): array
    {
        if (auth()->user()->hasRole('admin')) {
            return $ids;
        }
        return array_values(array_intersect(array_map('intval', $ids), $this->visibleOwnerIds()));
    }

    private function limitPropertyIds(array $ids): array
    {
        if (auth()->user()->hasRole('admin')) {
            return $ids;
        }
        return array_values(array_intersect(array_map('intval', $ids), $this->visiblePropertyIds()));
    }

    public function toggleStatus(User $user)
    {
        /** @var User $authUser */
        $authUser = auth()->user();

        if ($user->id === $authUser->id) {
            return back()->with('error', 'You cannot change your own account status.');
        }

        if ($user->isAdmin() && ! $authUser->isAdmin()) {
            abort(403, 'Only an admin can change another admin account status.');
        }

        $this->authorizeTarget($user);

        $newStatus = $user->is_active ? 'inactive' : 'active';
        if ($newStatus === 'active') {
            $user->reactivate();
        } else {
            $user->deactivate($authUser, (trim((string) request('reason')) ?: 'Deactivated from the Team page.'));
        }
        $user->update(['status' => $newStatus]);

        ActivityLogService::admin('user_status_changed', "{$authUser->name} set {$user->name}'s account to {$newStatus}.", 'users', [
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'severity'     => $newStatus === 'active' ? 'success' : 'warning',
            'metadata'     => ['new_status' => $newStatus],
        ]);

        $label = $newStatus === 'active' ? 'activated' : 'deactivated';

        return back()->with('success', "Account {$label} for {$user->name}.");
    }
}
