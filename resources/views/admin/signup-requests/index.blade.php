<x-admin-layout title="Signup Requests">
    <style>
        .sr-desktop{display:none}.sr-mobile{display:grid;gap:12px}
        .sr-card{background:#fff;border:1px solid #e1eaf1;border-radius:14px;padding:14px}
        .sr-name{font-weight:700;color:#0f172a;font-size:15px}
        .sr-meta{font-size:13px;color:#64748b;margin-top:3px;word-break:break-word}
        .sr-row{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
        .sr-row select{flex:1 1 140px;min-width:0}
        .sr-row .btn-primary{flex:1 1 100px;justify-content:center}
        .sr-reject{width:100%;margin-top:8px;padding:9px;border:1px solid #fecaca;border-radius:9px;background:#fff;color:#dc2626;font-weight:600;font-size:13px;cursor:pointer}
        .sr-table{width:100%;font-size:14px;border-collapse:collapse}
        .sr-table th{text-align:left;color:#64748b;font-weight:600;padding:0 12px 12px 0;border-bottom:1px solid #f1f5f9;white-space:nowrap}
        .sr-table td{padding:12px 12px 12px 0;border-bottom:1px solid #f8fafc;vertical-align:middle}
        @media(min-width:900px){.sr-desktop{display:block;overflow-x:auto}.sr-mobile{display:none}}
    </style>

    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h2 class="page-title">Signup Requests</h2>
            <p class="page-subtitle">Approve with a role and we'll email them a link to create their password (valid 24 hours).</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Phones & tablets --}}
    <div class="sr-mobile">
        @forelse($pendingUsers as $user)
            <div class="sr-card">
                <div class="sr-name">{{ $user->name }}</div>
                <div class="sr-meta"><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></div>
                <div class="sr-meta">{{ $user->phone_number ?: '-' }}</div>
                <div class="sr-meta">Wants: <strong>{{ ucfirst($user->requested_role ?? '-') }}</strong> &middot; {{ $user->created_at->diffForHumans() }}</div>
                <form method="post" action="{{ route('admin.signup-requests.approve', $user) }}" class="sr-row">
                    @csrf
                    <select name="role" class="input py-2 text-sm">
                        @foreach(\App\Models\User::ROLE_LABELS as $rk => $rl)
                            <option value="{{ $rk }}" @selected($rk === $user->requested_role)>{{ $rl }}</option>
                        @endforeach
                    </select>
                    <button class="btn-primary py-2 px-4 text-sm">Approve</button>
                </form>
                <form method="post" action="{{ route('admin.signup-requests.reject', $user) }}" onsubmit="return confirm('Reject this signup request?')">
                    @csrf
                    <button class="sr-reject">Reject</button>
                </form>
            </div>
        @empty
            <div class="card card-pad text-center text-sm text-slate-500">No pending signup requests.</div>
        @endforelse
    </div>

    {{-- Desktop --}}
    <div class="sr-desktop card card-pad">
        <table class="sr-table">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>Requested</th><th>Received</th><th>Role &amp; approve</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($pendingUsers as $user)
                <tr>
                    <td style="font-weight:600;color:#0f172a">{{ $user->name }}</td>
                    <td><a href="mailto:{{ $user->email }}">{{ $user->email }}</a></td>
                    <td>{{ $user->phone_number ?: '-' }}</td>
                    <td class="capitalize">{{ $user->requested_role ?? '-' }}</td>
                    <td style="color:#64748b;white-space:nowrap">{{ $user->created_at->diffForHumans() }}</td>
                    <td>
                        <form method="post" action="{{ route('admin.signup-requests.approve', $user) }}" style="display:flex;gap:8px;align-items:center">
                            @csrf
                            <select name="role" class="input py-1.5 text-xs" style="width:9rem">
                                @foreach(\App\Models\User::ROLE_LABELS as $rk => $rl)
                                    <option value="{{ $rk }}" @selected($rk === $user->requested_role)>{{ $rl }}</option>
                                @endforeach
                            </select>
                            <button class="btn-primary py-1.5 px-3 text-xs">Approve</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="{{ route('admin.signup-requests.reject', $user) }}" onsubmit="return confirm('Reject this signup request?')">
                            @csrf
                            <button style="color:#dc2626;font-weight:600;font-size:13px;background:none;border:0;cursor:pointer">Reject</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="padding:32px;text-align:center;color:#64748b">No pending signup requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
