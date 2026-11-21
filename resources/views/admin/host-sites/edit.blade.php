<x-admin-layout title="Host site branding">
    <div class="page-header">
        <div>
            <p class="eyebrow">Direct booking</p>
            <h1 class="page-title">Your host site</h1>
            <p class="page-subtitle">Give your published stays a consistent host identity while bookings continue to run securely through GuestHub.</p>
        </div>
        <a href="{{ route('admin.properties.index') }}" class="btn-secondary">Back to properties</a>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.host-site.update') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @method('put')
        <section class="card card-pad">
            <h2 class="section-title">Host identity</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="field-label md:col-span-2">Display name
                    <input name="display_name" value="{{ old('display_name', $site->display_name) }}" maxlength="120" required class="input">
                </label>
                <label class="field-label md:col-span-2">Host page address
                    <div class="flex items-center gap-2">
                        <span class="shrink-0 text-sm text-slate-500">{{ url('/h') }}/</span>
                        <input id="host-site-slug" name="slug" value="{{ old('slug', $site->slug) }}" data-check-url="{{ route('admin.host-site.check-slug') }}" minlength="3" maxlength="40" pattern="[a-z0-9]+(-[a-z0-9]+)*" required class="input">
                    </div>
                    <span class="field-help">Lowercase letters, numbers and hyphens. Your previous host-page address will redirect here.</span>
                    <span id="host-site-slug-status" class="field-help" aria-live="polite"></span>
                </label>
                <label class="field-label">Brand color
                    <input type="color" name="brand_color" value="{{ old('brand_color', $site->brand_color) }}" class="input h-12">
                </label>
                <label class="field-label">Support email
                    <input type="email" name="support_email" value="{{ old('support_email', $site->support_email) }}" maxlength="255" class="input" placeholder="support@example.com">
                </label>
                <label class="field-label md:col-span-2">Custom domain (optional)
                    <input name="custom_domain" value="{{ old('custom_domain', $site->custom_domain) }}" maxlength="253" class="input" placeholder="stay.example.com">
                    <span class="field-help">Enter a domain or subdomain you control. Changing it resets verification.</span>
                </label>
                @if($site->custom_domain)
                    <div class="md:col-span-2 rounded-xl border border-slate-200 p-4 text-sm">
                        <p class="font-semibold">DNS verification</p>
                        <p class="mt-1 text-slate-600">Create this TXT record:</p>
                        <p class="mt-2 font-mono text-xs">_guesthub-verification.{{ $site->custom_domain }}</p>
                        <p class="font-mono text-xs break-all">{{ $site->domain_verification_token }}</p>
                        <p class="mt-3 text-slate-600">Point your domain to <span class="font-mono">{{ $domainTarget }}</span> using the DNS record supplied by your hosting provider, and configure TLS/HTTPS for the custom domain.</p>
                        @if($site->domain_verified_at)
                            <p class="mt-2 font-semibold text-emerald-700">Ownership verified {{ $site->domain_verified_at->diffForHumans() }}</p>
                        @else
                            <button type="submit" name="_intent" value="verify_domain" formnovalidate class="btn-secondary mt-3">Check TXT verification</button>
                        @endif
                    </div>
                @endif
            </div>
            <label class="mt-6 flex items-center gap-3 text-sm font-medium">
                <input type="checkbox" name="show_powered_by" value="1" @checked(old('show_powered_by', $site->show_powered_by))>
                Show “Powered by GuestHub” in the host-site footer
            </label>
            <button class="btn-primary mt-6">Save branding</button>
        </section>

        <aside class="card card-pad">
            <h2 class="section-title">Host logo</h2>
            @if($site->logo_path)
                <img src="{{ url('/img/'.$site->logo_path) }}" alt="{{ $site->display_name }} logo" class="mb-4 max-h-24 max-w-full object-contain">
            @endif
            <label class="field-label">Upload logo
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="input">
                <span class="field-help">PNG, JPG or WebP image, up to 5 MB. A transparent PNG or SVG works best.</span>
            </label>
            <div class="mt-6 rounded-xl border border-slate-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preview</p>
                <div class="mt-3 flex items-center gap-3">
                    @if($site->logo_path)
                        <img src="{{ url('/img/'.$site->logo_path) }}" alt="" class="h-10 max-w-24 object-contain">
                    @else
                        <span class="grid h-10 w-10 place-items-center rounded-lg text-white" style="background:{{ $site->brand_color }}">H</span>
                    @endif
                    <strong>{{ $site->display_name }}</strong>
                </div>
            </div>
            <a href="{{ route('public.host.show', $site->slug) }}" target="_blank" rel="noopener" class="mt-4 inline-block text-sm font-semibold underline">View your public host page</a>
        </aside>
    </form>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('host-site-slug');
    const status = document.getElementById('host-site-slug-status');
    if (!input || !status) return;
    let requestController = null;
    input.addEventListener('blur', async () => {
        const slug = input.value.trim().toLowerCase();
        if (!slug) { status.textContent = ''; return; }
        if (requestController) requestController.abort();
        requestController = new AbortController();
        status.textContent = 'Checking address…';
        try {
            const url = new URL(input.dataset.checkUrl, window.location.origin);
            url.searchParams.set('slug', slug);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: requestController.signal});
            const result = await response.json();
            status.textContent = result.message + (result.suggestion ? ' Suggested: ' + result.suggestion : '');
            status.className = 'field-help ' + (result.available ? 'text-emerald-700' : 'text-amber-700');
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = 'Could not check the address right now.';
        }
    });
});
</script>
</x-admin-layout>
