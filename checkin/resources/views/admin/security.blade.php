<x-admin-layout title="Profile & Security">
    <div class="page-header">
        <div>
            <p class="eyebrow">Account controls</p>
            <h1 class="page-title">Profile and security</h1>
            <p class="page-subtitle">Review the signed-in admin account and the recommended security posture for production deployments.</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <section class="card card-pad">
            <h2 class="section-title">Admin profile</h2>
            <p class="section-copy">This installation uses custom Laravel authentication. Profile editing can be extended later without changing guest functionality.</p>
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <x-icon name="guests" class="mb-2 h-5 w-5 text-[#b08a45]" />
                    <p class="text-sm text-slate-500">Name</p>
                    <p class="mt-1 font-semibold text-slate-950">{{ auth()->user()->name }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <x-icon name="mail" class="mb-2 h-5 w-5 text-[#b08a45]" />
                    <p class="text-sm text-slate-500">Email</p>
                    <p class="mt-1 font-semibold text-slate-950">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </section>

        <aside class="card card-pad">
            <h2 class="section-title">Security checklist</h2>
            <div class="mt-4 grid gap-3 text-sm">
                @foreach([
                    'Use HTTPS in production.',
                    'Change the seeded admin password before launch.',
                    'Restrict photo ID access to authenticated admins.',
                    'Keep APP_DEBUG=false on the VPS.',
                    'Add 2FA before onboarding multiple staff members.',
                ] as $item)
                    <div class="flex gap-3 rounded-lg bg-slate-50 p-3"><x-icon name="check" class="mt-0.5 h-4 w-4 text-emerald-600" /><span class="text-slate-700">{{ $item }}</span></div>
                @endforeach
            </div>
        </aside>
    </div>

    <section class="mt-6 card card-pad">
        <p class="eyebrow">2FA readiness</p>
        <h2 class="mt-2 text-2xl font-semibold text-slate-950">Two-factor authentication</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">The UI is prepared for a security workflow, but two-factor authentication has not been enabled yet because this project is using lightweight custom auth instead of a full account-management starter kit. For production, add Laravel Fortify or Breeze profile features before giving access to multiple admins.</p>
    </section>
</x-admin-layout>
