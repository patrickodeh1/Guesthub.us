<?php

namespace App\Services\Channels;

use App\Models\PropertyListing;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

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
            'propertyType' => $listing->property_type ?: 'apartment',
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
            'checkInTimeStart' => $p?->checkin_time,
            'checkOutTime' => $p?->checkout_time,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Body for PUT /v1/listings/{id}/content. Amenities are a full replacement; local photo bytes are uploaded to Repull's
     * canonical photo storage before the content write.
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
            'details' => ['propertyTypeCategory' => $listing->property_type ?: 'apartment', 'roomTypeCategory' => $listing->room_type ?: 'entire_home'],
            'policies' => array_filter([
                'houseRules' => $this->clean($listing->house_rules),
                'cancellationPolicy' => $listing->cancellation_policy,
                'checkInTimeStart' => $p?->checkin_time,
                'checkOutTime' => $p?->checkout_time,
            ]),
            'pricing' => array_filter([
                'defaultDailyPrice' => $this->money($listing->base_rate_cents),
                'cleaningFee' => $this->money($listing->cleaning_fee_cents),
            ], fn ($v) => $v !== null),
        ];

        $mappedAmenities = $p?->amenities()->whereNotNull('repull_amenity_id')->get() ?? collect();
        $amenities = $mappedAmenities->where('active', true)->pluck('repull_amenity_id')->filter()->unique()->values()->all();
        if ($listing->amenities_sync_enabled) {
            // Repull replaces the complete canonical set when this key is present.
            $payload['amenities'] = $amenities;
        }

        if ($listing->rooms_sync_enabled) {
            $payload['rooms'] = $listing->listingRooms->map(function ($room) {
                $beds = is_array($room->beds) && $room->beds !== []
                    ? $room->beds
                    : (filled($room->bed_type) && (int) $room->bed_quantity > 0
                        ? [['bedType' => $room->bed_type, 'quantity' => (int) $room->bed_quantity]]
                        : []);

                return array_filter([
                    'roomType' => $room->room_type,
                    'roomNumber' => $room->room_number,
                    'roomName' => $room->room_name,
                    'beds' => $beds,
                    'isPrivate' => (bool) $room->is_private,
                ], fn ($value) => $value !== null && $value !== '');
            })->values()->all();
        }

        return array_filter($payload, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    public function hash(PropertyListing $listing): string
    {
        return hash('sha256', json_encode([
            $this->createPayload($listing),
            $this->contentPayload($listing),
            $listing->photos->sortBy('sort_order')->map(fn ($photo) => [$photo->id, $photo->path, $photo->alt, $photo->sort_order])->values()->all(),
        ]));
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
            'beds' => [$p?->beds, 'Total beds'],
            'propertyType' => [$listing->property_type, 'Property type'],
            'roomType' => [$listing->room_type, 'Room type'],
            'checkInTimeStart' => [$p?->checkin_time, 'Check-in time'],
            'checkOutTime' => [$p?->checkout_time, 'Check-out time'],
            'cancellationPolicy' => [$listing->cancellation_policy, 'Cancellation policy'],
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
    public function photoUrls(PropertyListing $listing): array
    {
        return $this->photos($listing);
    }

    private function photos(PropertyListing $listing): array
    {
        return $listing->photos
            ->map(fn ($photo) => array_filter([
                'url' => URL::signedRoute('channels.media.show', ['listingPhoto' => $photo->id]),
                'caption' => (string) ($photo->alt ?? ''),
                'localPhotoId' => (int) $photo->id,
            ], fn ($v) => $v !== ''))
            ->filter(fn ($photo) => isset($photo['url']))
            ->values()
            ->all();
    }
}
