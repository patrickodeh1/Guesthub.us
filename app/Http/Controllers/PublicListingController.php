<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\AvailabilitySearch;
use App\Services\FeatureGate;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicListingController extends Controller
{
    private const PER_PAGE = 24;

    public function browse(Request $request)
    {
        return $this->renderBrowse($request);
    }

    public function city(Request $request, string $city)
    {
        return $this->renderBrowse($request, $city);
    }

    public function show(string $listingSlug)
    {
        $listing = $this->publicListingQuery()
            ->where('listing_slug', $listingSlug)
            ->firstOrFail();

        $data = $this->present($listing);
        $canonical = route('public.listing.show', $listing->listing_slug);
        $metaTitle = $listing->meta_title ?: $listing->public_title;
        $metaDescription = $listing->meta_description ?: Str::limit(strip_tags((string) $listing->public_description), 300);
        $structuredData = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'VacationRental',
                'name' => $listing->public_title,
                'description' => strip_tags((string) $listing->public_description),
                'url' => $canonical,
                'image' => collect($data['photos'])->pluck('url')->all(),
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $data['city'],
                    'addressRegion' => $data['state'],
                    'addressCountry' => 'US',
                ],
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                ],
                'amenityFeature' => collect($data['amenities'])->map(fn ($amenity) => [
                    '@type' => 'LocationFeatureSpecification',
                    'name' => $amenity,
                    'value' => true,
                ])->values()->all(),
                'occupancy' => [
                    '@type' => 'QuantitativeValue',
                    'maxValue' => $listing->max_guests,
                ],
            ],
            $this->breadcrumbSchema([
                ['name' => 'Home', 'url' => route('public.stays.index')],
                ['name' => $data['city'], 'url' => route('public.stays.city', Str::slug($data['city']))],
                ['name' => $listing->public_title, 'url' => $canonical],
            ]),
        ];

        return view('public.listing', [
            'listing' => $listing,
            'public' => $data,
            'canonical' => $canonical,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'noindex' => request()->query() !== [],
            'structuredData' => $structuredData,
            'calendar' => app(AvailabilitySearch::class)->calendar(
                $listing,
                now($this->timezone($data['timezone']))->toDateString(),
                now($this->timezone($data['timezone']))->addDays(59)->toDateString(),
            ),
            'quoteUrl' => route('public.listing.quote', $listing->listing_slug),
        ]);
    }

    public function host(string $hostSlug)
    {
        $listings = $this->eligibleListings();
        $ownerListings = $listings->filter(fn (PropertyListing $listing) => $this->hostSlug($listing->property->owner) === $hostSlug);
        abort_if($ownerListings->isEmpty(), 404);

        $owner = $ownerListings->first()->property->owner;
        $title = $owner->host_name ?: $owner->name;
        $canonical = route('public.host.show', $hostSlug);
        $cards = $ownerListings->map(fn (PropertyListing $listing) => $this->present($listing))->values();
        $mapPoints = $this->mapPoints($cards);
        $schema = $this->itemListSchema($cards, $canonical, $title.' stays');
        $breadcrumb = $this->breadcrumbSchema([
            ['name' => 'Home', 'url' => route('public.stays.index')],
            ['name' => $title, 'url' => $canonical],
        ]);

        return view('public.browse', [
            'title' => $title.' stays',
            'intro' => 'Explore published stays from '.$title.'.',
            'metaDescription' => 'Explore '.count($cards).' published stays from '.$title.' on GuestHub.',
            'listings' => $this->paginate($cards, request()),
            'filters' => [],
            'canonical' => $canonical,
            'noindex' => request()->query() !== [],
            'structuredData' => [$schema, $breadcrumb],
            'mapPoints' => $mapPoints,
        ]);
    }

    public function quote(Request $request, string $listingSlug, QuoteService $quotes)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['nullable', 'in:card,manual'],
        ]);
        $listing = $this->findPublicListing($listingSlug);
        $paymentMethod = $data['payment_method'] ?? 'card';
        if (! $this->paymentMethodAvailable($listing, $paymentMethod)) {
            throw ValidationException::withMessages(['payment_method' => 'This payment option is not currently available.']);
        }
        $quote = $quotes->quote(
            $listing,
            $data['check_in'],
            $data['check_out'],
            (int) $data['guests'],
            $paymentMethod,
        );

        return response()->json($quote);
    }

    public function availability(Request $request, string $listingSlug)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'through' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $listing = $this->findPublicListing($listingSlug);
        $from = $data['from'] ?? now()->toDateString();
        $through = $data['through'] ?? now()->addDays(59)->toDateString();

        return response()->json([
            'dates' => app(AvailabilitySearch::class)->calendar($listing, $from, $through),
        ]);
    }

    public function sitemap()
    {
        $listings = $this->eligibleListings(false);
        $urls = collect([
            ['loc' => route('public.stays.index'), 'lastmod' => now()->toAtomString()],
        ]);
        $listings->each(function (PropertyListing $listing) use ($urls) {
            $urls->push([
                'loc' => route('public.listing.show', $listing->listing_slug),
                'lastmod' => ($listing->updated_at ?: $listing->published_at)->toAtomString(),
            ]);
        });
        $listings->map(fn (PropertyListing $listing) => $listing->property->city)
            ->filter()
            ->unique(fn ($city) => Str::slug($city))
            ->each(fn ($city) => $urls->push([
                'loc' => route('public.stays.city', Str::slug($city)),
                'lastmod' => now()->toAtomString(),
            ]));
        $listings->map(fn (PropertyListing $listing) => $listing->property->owner)
            ->filter()
            ->unique('id')
            ->each(fn (User $owner) => $urls->push([
                'loc' => route('public.host.show', $this->hostSlug($owner)),
                'lastmod' => now()->toAtomString(),
            ]));

        return response()
            ->view('public.sitemap', ['urls' => $urls->unique('loc')->values()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $agents = ['Googlebot', 'Bingbot', 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended'];
        $content = '';
        foreach ($agents as $agent) {
            $content .= "User-agent: {$agent}\n";
        }
        $content .= "Allow: /\n";
        $content .= $this->robotsDisallowRules();
        $content .= "\nUser-agent: *\nAllow: /\n".$this->robotsDisallowRules();
        $content .= "\nSitemap: ".route('public.sitemap', [], true)."\n";

        return response($content)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function llms()
    {
        $content = "# GuestHub public stays\n\n"
            .'Browse published short-term rental listings at '.route('public.stays.index', [], true).".\n"
            ."Each listing contains public descriptions, amenities, approximate locations, and availability information.\n";

        return response($content)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function indexNowKey(string $key)
    {
        $configured = (string) config('services.indexnow.key');
        abort_unless($configured !== '' && hash_equals($configured, $key), 404);

        return response($configured, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function renderBrowse(Request $request, ?string $citySlug = null)
    {
        $filters = $request->validate([
            'city' => ['nullable', 'string', 'max:120'],
            'check_in' => ['nullable', 'required_with:check_out', 'date_format:Y-m-d'],
            'check_out' => ['nullable', 'required_with:check_in', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:100000', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:100000', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/'],
        ]);
        if (isset($filters['price_min'], $filters['price_max']) && $filters['price_min'] > $filters['price_max']) {
            throw ValidationException::withMessages(['price_max' => 'The maximum price must not be below the minimum price.']);
        }

        $citySlug = $citySlug
            ? Str::slug($citySlug)
            : (isset($filters['city']) ? Str::slug($filters['city']) : null);
        $query = $this->publicListingQuery();

        $all = $query->get();
        if ($citySlug) {
            $all = $all->filter(fn (PropertyListing $listing) => Str::slug((string) $listing->property->city) === $citySlug);
            abort_if($all->isEmpty(), 404);
        }
        if (isset($filters['price_min'])) {
            $minimum = $this->dollarsToCents((string) $filters['price_min']);
            $all = $all->filter(fn (PropertyListing $listing) => $listing->base_rate_cents >= $minimum);
        }
        if (isset($filters['price_max'])) {
            $maximum = $this->dollarsToCents((string) $filters['price_max']);
            $all = $all->filter(fn (PropertyListing $listing) => $listing->base_rate_cents <= $maximum);
        }
        if (isset($filters['guests'])) {
            $all = $all->filter(fn (PropertyListing $listing) => $listing->max_guests >= (int) $filters['guests']);
        }
        if (isset($filters['check_in'], $filters['check_out'])) {
            $guestCount = (int) ($filters['guests'] ?? 1);
            $all = $all->filter(function (PropertyListing $listing) use ($filters, $guestCount) {
                try {
                    app(QuoteService::class)->quote($listing, $filters['check_in'], $filters['check_out'], $guestCount, 'manual');

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            });
        }

        $cards = $all->map(fn (PropertyListing $listing) => $this->present($listing))->values();
        $cityName = $citySlug
            ? ($all->first()?->property?->city ?: Str::headline(str_replace('-', ' ', $citySlug)))
            : null;
        $canonical = $citySlug
            ? route('public.stays.city', Str::slug($citySlug))
            : route('public.stays.index');
        $title = $cityName ? 'Stays in '.$cityName : 'Find a stay';
        $intro = $cityName
            ? 'Explore available short-term stays in '.$cityName.'.'
            : 'Discover welcoming places to stay, with availability and pricing from the host.';
        $schema = $this->itemListSchema($cards, $canonical, $title);
        $mapPoints = $this->mapPoints($cards);
        $breadcrumbs = [['name' => 'Home', 'url' => route('public.stays.index')]];
        if ($cityName) {
            $breadcrumbs[] = ['name' => $cityName, 'url' => $canonical];
        }
        $metaDescription = $cityName
            ? 'Explore '.count($cards).' published stays in '.$cityName.' with approximate locations and nightly pricing.'
            : 'Browse published GuestHub stays with current availability and clear nightly pricing.';

        return view('public.browse', [
            'title' => $title,
            'intro' => $intro,
            'metaDescription' => $metaDescription,
            'listings' => $this->paginate($cards, $request),
            'filters' => $filters + ['city' => $cityName],
            'canonical' => $canonical,
            'noindex' => $request->query() !== [],
            'structuredData' => [$schema, $this->breadcrumbSchema($breadcrumbs)],
            'mapPoints' => $mapPoints,
        ]);
    }

    private function publicListingQuery(bool $withDetails = true)
    {
        $feature = Feature::query()->where('key', 'direct_booking_site')->first();
        $query = PropertyListing::query()->whereNotNull('published_at');
        if ($withDetails) {
            $query->with([
                'photos',
                'property' => fn ($query) => $query
                    ->select(['id', 'owner_id', 'city', 'state', 'beds', 'baths', 'latitude', 'longitude', 'timezone', 'checkin_time', 'active'])
                    ->with([
                        'owner:id,host_name,name,stripe_account_id,stripe_charges_enabled',
                        'owner.hostPaymentMethods:id,owner_id,enabled',
                        'amenities' => fn ($amenities) => $amenities->where('active', true)->select(['id', 'property_id', 'title']),
                    ]),
            ]);
        } else {
            $query->with([
                'property' => fn ($query) => $query
                    ->select(['id', 'owner_id', 'city'])
                    ->with('owner:id,host_name,name'),
            ]);
        }
        if (! $feature || ! $feature->active) {
            return $query->whereRaw('1 = 0');
        }

        $now = now();

        return $query->whereHas('property', function ($property) use ($feature, $now) {
            $property->where('active', true)->whereNotNull('owner_id');
            $hostFeatureExists = function ($hostFeatureQuery, bool $enabled) use ($feature, $now) {
                $hostFeatureQuery->selectRaw('1')
                    ->from('host_features')
                    ->whereColumn('host_features.user_id', 'properties.owner_id')
                    ->where('host_features.feature_key', $feature->key)
                    ->where('host_features.enabled', $enabled)
                    ->where(fn ($query) => $query->whereNull('host_features.starts_at')->orWhere('host_features.starts_at', '<=', $now))
                    ->where(fn ($query) => $query->whereNull('host_features.ends_at')->orWhere('host_features.ends_at', '>=', $now));
            };
            if ($feature->is_free) {
                $property->whereNotExists(fn ($hostFeatureQuery) => $hostFeatureExists($hostFeatureQuery, false));
            } else {
                $property->whereExists(fn ($hostFeatureQuery) => $hostFeatureExists($hostFeatureQuery, true));
            }
        });
    }

    private function eligibleListings(bool $withDetails = true): Collection
    {
        return $this->publicListingQuery($withDetails)->get()->values();
    }

    private function findPublicListing(string $slug): PropertyListing
    {
        $listing = $this->publicListingQuery()->where('listing_slug', $slug)->firstOrFail();

        return $listing;
    }

    private function paymentMethodAvailable(PropertyListing $listing, string $method): bool
    {
        $owner = $listing->property->owner;
        if ($method === 'card') {
            return filled($owner->stripe_account_id) && $owner->stripe_charges_enabled;
        }

        return $method === 'manual'
            && FeatureGate::enabled($owner, 'manual_payments')
            && $owner->hostPaymentMethods->contains(fn ($paymentMethod) => $paymentMethod->enabled);
    }

    private function dollarsToCents(string $amount): int
    {
        preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', $amount, $parts);

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    private function present(PropertyListing $listing): array
    {
        $property = $listing->property;
        $owner = $property->owner;
        $photos = $listing->photos->map(fn ($photo) => [
            'url' => Storage::disk('public')->url($photo->path),
            'thumbnail_url' => Storage::disk('public')->url($photo->thumbnail_path),
            'alt' => $photo->alt ?: $listing->public_title,
            'width' => $photo->width ?: 1200,
            'height' => $photo->height ?: 800,
        ])->values()->all();

        return [
            'id' => $listing->getKey(),
            'slug' => $listing->listing_slug,
            'title' => $listing->public_title,
            'description' => $listing->public_description,
            'base_rate_cents' => $listing->base_rate_cents,
            'max_guests' => $listing->max_guests,
            'cancellation_policy' => match ($listing->cancellation_policy) {
                'flexible' => 'Cancel for a full refund up to 24 hours before arrival.',
                'strict' => 'Cancel up to 7 days before arrival for a 50% refund.',
                default => 'Cancel for a full refund up to 5 days before arrival.',
            },
            'bedrooms' => $listing->bedrooms ?? $property->beds,
            'baths' => $property->baths,
            'city' => $property->city ?: 'United States',
            'state' => $property->state,
            'latitude' => round((float) $property->latitude, 2),
            'longitude' => round((float) $property->longitude, 2),
            'timezone' => $property->timezone ?: config('app.timezone'),
            'amenities' => $property->amenities->pluck('title')->filter()->values()->all(),
            'photos' => $photos,
            'url' => route('public.listing.show', $listing->listing_slug),
            'host_name' => $owner->host_name ?: $owner->name,
            'host_url' => route('public.host.show', $this->hostSlug($owner)),
            'payment_options' => [
                'card' => filled($owner->stripe_account_id) && $owner->stripe_charges_enabled,
                'manual' => FeatureGate::enabled($owner, 'manual_payments')
                    && $owner->hostPaymentMethods->contains(fn ($paymentMethod) => $paymentMethod->enabled),
            ],
            'published_at' => $listing->published_at,
        ];
    }

    private function hostSlug(User $owner): string
    {
        return Str::slug($owner->host_name ?: $owner->name).'-'.$owner->getKey();
    }

    private function timezone(string $timezone): string
    {
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone');
    }

    private function robotsDisallowRules(): string
    {
        return "Disallow: /admin\nDisallow: /guest\nDisallow: /cleaning\n"
            ."Disallow: /checkout\nDisallow: /stay/*/checkout\n"
            ."Disallow: /stay/*/quote\nDisallow: /stay/*/availability\n";
    }

    private function itemListSchema(Collection $cards, string $url, string $name): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'url' => $url,
            'itemListElement' => $cards->values()->map(fn (array $listing, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $listing['url'],
                'name' => $listing['title'],
            ])->all(),
        ];
    }

    private function mapPoints(Collection $cards): array
    {
        return $cards->map(fn (array $stay) => [
            'latitude' => $stay['latitude'],
            'longitude' => $stay['longitude'],
            'title' => $stay['title'],
            'url' => $stay['url'],
            'city' => $stay['city'],
        ])->values()->all();
    }

    private function breadcrumbSchema(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    private function paginate(Collection $cards, Request $request): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $items = $cards->forPage($page, self::PER_PAGE)->values();

        return new LengthAwarePaginator($items, $cards->count(), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
