<?php

namespace App\Services\Channels;

use App\Models\PropertyListing;
use Illuminate\Support\Str;

/**
 * Canonical GuestHub listing  ->  Repull payloads.
 *
 * Source of truth is PropertyListing + Property + ListingPhoto. Nothing here
 * touches the network. Field names follow Repull's "Create a listing" and
 * "PUT /v1/listings/{id}/content" docs (docs/CHANNEL_SYNC_PLAN.md section 4).
 */
class ListingContentMapper
{
    /** Channels that sell nightly stays and therefore cannot carry long-term-only listings. */
    private const NIGHTLY = ['airbnb', 'booking', 'vrbo'];

    /** @return array<string,mixed> body for POST /v1/listings */
    public function createPayload(PropertyListing $listing): array
    {
        $p = $listing->property;
        $street = $this->street($listing);

        return array_filter([
            'name' => $this->title($listing),
            'propertyType' => 'apartment',
            'street' => $street,
            'city' => $p?->city,
            'state' => $p?->state,
            'postalCode' => $p?->zip,
            'countryCode' => $this->country($listing),
            'lat' => $p?->latitude !== null ? (float) $p->latitude : null,
            'lng' => $p?->longitude !== null ? (float) $p->longitude : null,
            'bedrooms' => $listing->bedrooms,
            'beds' => $p?->beds,
            'bathrooms' => $p?->baths !== null ? (float) $p->baths : null,
            'personCapacity' => $listing->max_guests,
            'summary' => $this->summary($listing),
            'description' => $this->clean($listing->public_description),
            'defaultDailyPrice' => $this->money($listing->base_rate_cents),
            'cleaningFee' => $this->money($listing->cleaning_fee_cents),
            'cancellationPolicy' => $listing->cancellation_policy,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Body for PUT /v1/listings/{id}/content. NOTE: `photos` and `amenities` are FULL
     * replacements on Repull, so the complete set is sent every time.
     *
     * @return array<string,mixed>
     */
    public function contentPayload(PropertyListing $listing): array
    {
        $p = $listing->property;
        $payload = [
            'title' => $this->title($listing),
            'summary' => $this->summary($listing),
            'description' => $this->clean($listing->public_description),
            'address' => array_filter(['state' => $p?->state, 'postalCode' => $p?->zip]),
            'occupancy' => array_filter([
                'maxGuests' => $listing->max_guests,
                'bedrooms' => $listing->bedrooms,
                'beds' => $p?->beds,
                'bathrooms' => $p?->baths !== null ? (float) $p->baths : null,
            ], fn ($v) => $v !== null),
            'details' => ['propertyTypeCategory' => 'apartment', 'roomTypeCategory' => 'entire_home'],
            'policies' => array_filter(['houseRules' => $this->clean($listing->house_rules)]),
            'pricing' => array_filter([
                'defaultDailyPrice' => $this->money($listing->base_rate_cents),
                'cleaningFee' => $this->money($listing->cleaning_fee_cents),
            ], fn ($v) => $v !== null),
        ];

        $photos = $this->photos($listing);
        if ($photos !== []) {
            $payload['photos'] = $photos;
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    public function hash(PropertyListing $listing): string
    {
        return hash('sha256', json_encode([$this->createPayload($listing), $this->contentPayload($listing)]));
    }

    /**
     * Fields a channel needs before we will even try to publish.
     * Returns field => human label. Empty array = ready.
     *
     * @return array<string,string>
     */
    public function missing(PropertyListing $listing, string $channel): array
    {
        $p = $listing->property;
        $missing = [];

        if (in_array($channel, self::NIGHTLY, true) && $listing->listing_type === 'long_term') {
            $missing['listing_type'] = 'This listing is long-term only; nightly channels need short-term stays enabled';
        }

        $isUs = strtoupper($this->country($listing)) === 'US';

        $required = [
            'title' => [$this->title($listing), 'Listing title'],
            'description' => [$this->clean($listing->public_description), 'Description'],
            'street' => [$this->street($listing), 'Street address'],
            'city' => [$p?->city, 'City'],
            'lat' => [$p?->latitude, 'Map latitude'],
            'lng' => [$p?->longitude, 'Map longitude'],
            'personCapacity' => [$listing->max_guests, 'Max guests'],
            'bedrooms' => [$listing->bedrooms, 'Bedrooms'],
            'bathrooms' => [$p?->baths, 'Bathrooms'],
            'price' => [$listing->base_rate_cents, 'Nightly rate'],
        ];
        if ($isUs) {
            $required['state'] = [$p?->state, 'State'];
            $required['postalCode'] = [$p?->zip, 'ZIP / postal code'];
        }

        foreach ($required as $key => [$value, $label]) {
            if ($value === null || $value === '' || $value === 0 && in_array($key, ['personCapacity', 'price'], true)) {
                $missing[$key] = $label;
            }
        }

        if ($this->photos($listing) === []) {
            $missing['photos'] = 'At least one photo';
        }

        return $missing;
    }

    /** Warnings (not blockers): things we stripped from the text before pushing. @return list<string> */
    public function warnings(PropertyListing $listing): array
    {
        $out = [];
        foreach (['description' => $listing->public_description, 'house rules' => $listing->house_rules] as $label => $text) {
            [, $removed] = ContentSanitizer::forOta($text);
            foreach ($removed as $kind) {
                $out[] = "Removed a {$kind} from the {$label} (channels reject contact details and links).";
            }
        }

        return $out;
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function title(PropertyListing $listing): string
    {
        return (string) ($listing->public_title ?: $listing->property?->guest_name ?: $listing->property?->name ?: '');
    }

    private function summary(PropertyListing $listing): ?string
    {
        $text = $this->clean($listing->public_description);

        return $text === '' ? null : Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($text))), 500, '');
    }

    private function clean(?string $text): string
    {
        return ContentSanitizer::forOta($text)[0];
    }

    private function country(PropertyListing $listing): string
    {
        return (string) ($listing->property?->country ?: 'US');
    }

    /** `properties.address` may hold the whole one-line address; Repull wants the street only. */
    private function street(PropertyListing $listing): string
    {
        $address = trim((string) $listing->property?->address);

        return $address === '' ? '' : trim(Str::before($address, ','));
    }

    private function money(?int $cents): ?float
    {
        return $cents === null ? null : round($cents / 100, 2);
    }

    /** @return list<array{url:string,caption:string}> public https URLs (Repull downloads them) */
    private function photos(PropertyListing $listing): array
    {
        return $listing->photos
            ->map(fn ($photo) => array_filter([
                'url' => url('/img/'.ltrim((string) $photo->path, '/')),
                'caption' => (string) ($photo->alt ?? ''),
            ], fn ($v) => $v !== ''))
            ->filter(fn ($photo) => isset($photo['url']))
            ->values()
            ->all();
    }
}
