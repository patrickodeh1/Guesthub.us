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
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', Rule::in(User::ROLES)],
            'phone'    => ['nullable', 'string', 'max:50'],
            'notes'    => ['nullable', 'string', 'max:1000'],
        ]);

        $role = $data['role'];
        unset($data['role']);
        $data['password']   = Hash::make($data['password']);
        $data['status']     = 'active';
        $data['created_by'] = auth()->id();

        $newUser = User::create($data);
        $newUser->assignRole($role);

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

        return view('admin.users.show', compact('user', 'recentLogs'));
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        /** @var User $authUser */
        $authUser = auth()->user();

        if ($user->isAdmin() && ! $authUser->isAdmin()) {
            abort(403, 'Only an admin can edit another admin account.');
        }

        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['required', Rule::in(User::ROLES)],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['string', 'min:8', 'confirmed'];
        }

        $data = $request->validate($rules);

        $oldRole = $user->getRoleNames()->first();
        $newRole = $data['role'];
        unset($data['role']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
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

        $name = $user->name;
        $user->delete();

        ActivityLogService::admin('user_deleted', "{$authUser->name} deleted user account: {$name}.", 'users', [
            'severity' => 'warning',
            'metadata' => ['deleted_name' => $name],
        ]);

        return redirect()->route('admin.users.index')->with('success', "User account for {$name} has been removed.");
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

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
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
