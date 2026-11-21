<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\HostSite;
use App\Models\LeaseApplication;
use App\Models\PropertyListing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom hostnames serve only the verified owner's public booking site.
 * Admin, API, webhook and other hosts are never exposed through a host domain.
 */
class RestrictCustomHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($host === $appHost || $host === 'www.'.$appHost || 'www.'.$host === $appHost) {
            return $next($request);
        }

        $site = HostSite::query()
            ->where('custom_domain', $host)
            ->whereNotNull('domain_verified_at')
            ->first();

        abort_unless($site, 404, 'This hostname is not configured for a verified GuestHub site.');

        $routeName = (string) optional($request->route())->getName();
        if (in_array($routeName, ['public.host.custom-domain', 'public.sitemap', 'public.robots', 'public.llms'], true)) {
            return $next($request);
        }

        if ($routeName === 'public.host.show') {
            abort_unless((string) $request->route('host_slug') === (string) $site->slug, 404);
            return $next($request);
        }

        // Public listing, embed and quote routes must resolve to this host's
        // own listing. This also covers checkout start and availability calls.
        if (str_starts_with($routeName, 'public.listing.') || str_starts_with($routeName, 'public.checkout.')) {
            $listingSlug = $request->route('listing_slug');
            if ($listingSlug !== null) {
                $owned = PropertyListing::query()
                    ->where('listing_slug', (string) $listingSlug)
                    ->whereHas('property', fn ($query) => $query->where('owner_id', $site->user_id))
                    ->exists();
                abort_unless($owned, 404);
            }

            $bookingId = $request->route('booking_id');
            if ($bookingId !== null) {
                $owned = Booking::query()
                    ->whereKey($bookingId)
                    ->whereHas('property', fn ($query) => $query->where('owner_id', $site->user_id))
                    ->exists();
                abort_unless($owned, 404);
            }

            return $next($request);
        }

        if (str_starts_with($routeName, 'public.lease-application.') || str_starts_with($routeName, 'public.lease-offer.')) {
            $listingSlug = $request->route('listing_slug');
            if ($listingSlug !== null) {
                $owned = PropertyListing::query()
                    ->where('listing_slug', (string) $listingSlug)
                    ->whereHas('property', fn ($query) => $query->where('owner_id', $site->user_id))
                    ->exists();
                abort_unless($owned, 404);
            }

            $applicationId = $request->route('application');
            if ($applicationId !== null) {
                $owned = LeaseApplication::query()
                    ->whereKey($applicationId)
                    ->whereHas('property', fn ($query) => $query->where('owner_id', $site->user_id))
                    ->exists();
                abort_unless($owned, 404);
            }

            return $next($request);
        }

        // Images used by the host and listing templates are public assets.
        $path = ltrim($request->path(), '/');
        if (str_starts_with($path, 'img/') || str_starts_with($path, 'og/') || str_starts_with($path, 'storage/') || str_starts_with($path, 'build/') || str_starts_with($path, 'css/') || str_starts_with($path, 'js/') || str_starts_with($path, 'fonts/') || $path === 'favicon.ico') {
            return $next($request);
        }

        abort(404, 'This route is not available on a host custom domain.');
    }
}
