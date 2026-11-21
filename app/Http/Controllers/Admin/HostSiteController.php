<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HostSite;
use App\Models\HostSiteAlias;
use App\Services\FeatureGate;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HostSiteController extends Controller
{
    private const RESERVED = [
        'admin', 'guest', 'cleaning', 'stay', 'stays', 'h', 'embed', 'ical',
        'webhooks', 'login', 'api', 'welcome', 'storage', 'build', 'sitemap',
        'robots', 'llms', 'checkout', 'settings',
    ];

    public function edit(Request $request)
    {
        $owner = $request->user();
        abort_unless($owner->hasAnyRole(['admin', 'owner', 'company']), 403);
        abort_unless(FeatureGate::enabled($owner, 'direct_booking_site'), 403, 'Host-branded booking sites are not enabled for this account.');

        $site = HostSite::query()->firstOrCreate(
            ['user_id' => $owner->id],
            [
                'slug' => $this->uniqueSlug(Str::slug($owner->host_name ?: $owner->name).'-'.$owner->id),
                'display_name' => $owner->host_name ?: $owner->name,
                'brand_color' => '#17221e',
                'show_powered_by' => true,
            ],
        );

        return view('admin.host-sites.edit', ['site' => $site, 'domainTarget' => config('services.host_sites.domain_target', parse_url(config('app.url'), PHP_URL_HOST))]);
    }

    public function update(Request $request)
    {
        $owner = $request->user();
        abort_unless($owner->hasAnyRole(['admin', 'owner', 'company']), 403);
        abort_unless(FeatureGate::enabled($owner, 'direct_booking_site'), 403, 'Host-branded booking sites are not enabled for this account.');

        $site = HostSite::query()->firstOrCreate(
            ['user_id' => $owner->id],
            [
                'slug' => $this->uniqueSlug(Str::slug($owner->host_name ?: $owner->name).'-'.$owner->id),
                'display_name' => $owner->host_name ?: $owner->name,
            ],
        );

        $data = $request->validate([
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'display_name' => ['required', 'string', 'max:120'],
            'brand_color' => ['required', 'regex:/\A#[0-9A-Fa-f]{6}\z/'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'show_powered_by' => ['nullable', 'boolean'],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/\\A(?=.{4,253}\\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}\\z/i', Rule::unique('host_sites', 'custom_domain')->ignore($site->id)],
        ]);

        if (in_array($data['slug'], self::RESERVED, true)) {
            throw ValidationException::withMessages(['slug' => 'That address is reserved. Choose another.']);
        }

        $newSlug = $this->uniqueSlug($data['slug'], $site);
        if ($site->slug !== $newSlug) {
            HostSiteAlias::query()->firstOrCreate([
                'host_site_id' => $site->id,
                'slug' => $site->slug,
            ]);
            HostSiteAlias::query()->where('slug', $newSlug)->delete();
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('host-sites/'.$owner->id, 'public');
            MediaService::register($path, $request->file('logo')->getClientOriginalName(), $request->file('logo')->getSize(), 'Host Site Logos');
            if ($site->logo_path && $site->logo_path !== $path) {
                Storage::disk('public')->delete($site->logo_path);
            }
            $site->logo_path = $path;
        }

        $customDomain = strtolower(rtrim(trim((string) ($data['custom_domain'] ?? '')), '.'));
        if ($customDomain !== (string) $site->custom_domain) {
            $site->custom_domain = $customDomain !== '' ? $customDomain : null;
            $site->domain_verification_token = $customDomain !== '' ? Str::random(48) : null;
            $site->domain_verified_at = null;
        }

        $site->fill([
            'slug' => $newSlug,
            'display_name' => trim($data['display_name']),
            'brand_color' => $data['brand_color'],
            'support_email' => $data['support_email'] ?? null,
            'show_powered_by' => $request->boolean('show_powered_by'),
        ])->save();

        return back()->with('success', 'Your host site branding has been saved.');
    }


    public function verifyDomain(Request $request)
    {
        $owner = $request->user();
        $site = HostSite::query()->where('user_id', $owner->id)->firstOrFail();
        abort_unless($site->custom_domain && $site->domain_verification_token, 422, 'Add a custom domain first.');

        $records = function_exists('dns_get_record')
            ? @dns_get_record('_guesthub-verification.'.$site->custom_domain, DNS_TXT)
            : [];
        $verified = collect($records ?: [])->contains(function ($record) use ($site) {
            $txt = (string) ($record['txt'] ?? '');
            $entries = (array) ($record['entries'] ?? []);
            return hash_equals($site->domain_verification_token, $txt)
                || in_array($site->domain_verification_token, $entries, true);
        });

        if (! $verified) {
            return back()->withErrors(['custom_domain' => 'The TXT verification record was not found yet. DNS changes can take time to propagate.']);
        }

        $site->forceFill(['domain_verified_at' => now()])->save();

        return back()->with('success', 'Custom domain ownership verified. Configure DNS routing and a valid TLS certificate before sharing the domain.');
    }

    private function uniqueSlug(string $desired, ?HostSite $current = null): string
    {
        $base = Str::slug($desired);
        if (strlen($base) < 3) {
            $base = 'host-site';
        }
        $candidate = Str::limit($base, 40, '');
        $suffix = 2;

        while (
            HostSite::query()->where('slug', $candidate)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists()
            || HostSiteAlias::query()->where('slug', $candidate)->where('host_site_id', '!=', $current?->id ?? 0)->exists()
        ) {
            $tail = '-'.$suffix++;
            $candidate = Str::limit($base, 40 - strlen($tail), '').$tail;
        }

        return $candidate;
    }
}
