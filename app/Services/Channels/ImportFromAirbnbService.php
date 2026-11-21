<?php

namespace App\Services\Channels;

use App\Models\Amenity;
use App\Models\ListingPhoto;
use App\Models\ListingRoom;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Services\MediaService;
use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class ImportFromAirbnbService
{
    public function __construct(private readonly RepullClient $client) {}

    public function importInto(Property $property, string $repullListingId, string $airbnbConnectionId): PropertyListing
    {
        $listing = $property->listing()->firstOrNew();
        if ($listing->exists && filled($listing->published_at)) {
            throw new ChannelPublishBlocked('Import is only available for an unpublished draft. Published GuestHub listings are never overwritten by an Airbnb pull.');
        }

        $pullResult = $this->client->pullFromAirbnb($repullListingId, $airbnbConnectionId);
        if (data_get($pullResult, 'refreshedFromChannel') === false || data_get($pullResult, 'data.refreshedFromChannel') === false) {
            throw new RepullException('Repull could not refresh this listing from Airbnb. Check the account connection and try again later.', 'airbnb_refresh_failed');
        }
        $response = $this->client->getListing($repullListingId);
        $record = data_get($response, 'data') ?: $response;
        $content = (array) data_get($record, 'content', []);
        $details = (array) data_get($record, 'details', []);
        $policies = (array) data_get($record, 'policies', []);
        $title = trim((string) ($content['name'] ?? $content['title'] ?? $record['name'] ?? $property->name));
        $description = trim((string) ($content['description'] ?? $content['summary'] ?? $content['space'] ?? ''));

        $listing->fill([
            'listing_slug' => $listing->listing_slug ?: $this->availableSlug($title, $property),
            'public_title' => $title,
            'public_description' => $description,
            'max_guests' => $details['personCapacity'] ?? $details['maxGuests'] ?? $listing->max_guests,
            'bedrooms' => $details['bedrooms'] ?? $listing->bedrooms,
            'property_type' => $this->enum($details['propertyTypeCategory'] ?? $details['propertyType'] ?? null,
                ['apartment', 'house', 'condo', 'townhouse', 'villa', 'guesthouse', 'hotel', 'other'], 'other'),
            'room_type' => $this->enum($details['roomTypeCategory'] ?? $details['roomType'] ?? null,
                ['entire_home', 'private_room', 'shared_room', 'hotel_room'], 'entire_home'),
            'house_rules' => $content['houseRules'] ?? $content['house_rules'] ?? $listing->house_rules,
            'cancellation_policy' => $this->enum($policies['cancellationPolicy'] ?? $details['cancellationPolicy'] ?? null,
                ['flexible', 'moderate', 'strict'], $listing->cancellation_policy ?: 'moderate'),
            'rooms_sync_enabled' => ! empty($record['rooms']),
            'repull_listing_id' => $repullListingId,
            'published_at' => null,
        ]);

        $dailyPrice = $details['defaultDailyPrice'] ?? $details['basePrice'] ?? null;
        if (is_numeric($dailyPrice) && (float) $dailyPrice > 0) {
            $listing->base_rate_cents = (int) round((float) $dailyPrice * 100);
        }
        $listing->save();

        $address = (array) ($details['address'] ?? $record['address'] ?? []);
        $propertyData = array_filter([
            'beds' => isset($details['beds']) ? (int) $details['beds'] : null,
            'baths' => isset($details['bathrooms']) ? (float) $details['bathrooms'] : null,
            'checkin_time' => $this->time($details['checkInTimeStart'] ?? null),
            'checkout_time' => $this->time($details['checkOutTime'] ?? null),
            'address' => $address['street'] ?? $address['addressLine1'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? $address['province'] ?? null,
            'zip' => $address['postalCode'] ?? $address['zip'] ?? null,
            'country' => $address['country'] ?? null,
            'latitude' => $address['latitude'] ?? $details['latitude'] ?? null,
            'longitude' => $address['longitude'] ?? $details['longitude'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        if ($propertyData !== []) {
            $property->forceFill($propertyData)->save();
        }

        $amenityIds = collect($record['amenities'] ?? $content['amenities'] ?? [])
            ->map(fn ($amenity) => is_array($amenity) ? ($amenity['id'] ?? $amenity['amenityId'] ?? null) : $amenity)
            ->filter(fn ($id) => is_string($id) && preg_match('/\A[a-z0-9_]+\z/', $id))
            ->unique()
            ->values();
        foreach ($amenityIds as $amenityId) {
            Amenity::query()->updateOrCreate(
                ['property_id' => $property->id, 'repull_amenity_id' => $amenityId],
                ['title' => Str::headline($amenityId), 'active' => true],
            );
        }
        if ($amenityIds->isNotEmpty()) {
            $listing->forceFill(['amenities_sync_enabled' => true])->save();
        }

        $rooms = (array) ($record['rooms'] ?? $content['rooms'] ?? $details['rooms'] ?? []);
        if ($rooms !== []) {
            $listing->listingRooms()->delete();
            $bedTotal = 0;
            foreach ($rooms as $index => $room) {
                $beds = (array) ($room['beds'] ?? []);
                $firstBed = $beds[0] ?? [];
                $quantity = (int) ($firstBed['quantity'] ?? 0);
                $bedTotal += array_sum(array_map(fn ($bed) => (int) ($bed['quantity'] ?? 0), $beds));
                ListingRoom::create([
                    'listing_id' => $listing->id,
                    'room_type' => $this->enum($room['roomType'] ?? null,
                        ['bedroom', 'full_bathroom', 'half_bathroom', 'living_room', 'kitchen', 'studio', 'dining_room', 'family_room', 'office', 'garage', 'laundry_room', 'entrance_to_home', 'recreation_area', 'outdoor_space'], 'bedroom'),
                    'room_number' => $room['roomNumber'] ?? ($index + 1),
                    'room_name' => $room['roomName'] ?? null,
                    'bed_type' => $firstBed['bedType'] ?? null,
                    'bed_quantity' => $quantity > 0 ? $quantity : null,
                    'beds' => array_values(array_filter($beds, fn ($bed) => is_array($bed) && filled($bed['bedType'] ?? null) && (int) ($bed['quantity'] ?? 0) > 0)),
                    'is_private' => (bool) ($room['isPrivate'] ?? true),
                ]);
            }
            if ($bedTotal > 0) {
                $property->forceFill(['beds' => $bedTotal])->save();
            }
        }

        $this->replacePhotos($listing, $property, (array) ($record['photos'] ?? $content['photos'] ?? $details['photos'] ?? []));

        return $listing->fresh(['property', 'photos', 'listingRooms']);
    }

    private function replacePhotos(PropertyListing $listing, Property $property, array $photos): void
    {
        foreach ($listing->photos()->get() as $old) {
            if ($old->source_channel === 'airbnb' && (string) $old->source_listing_id === (string) $listing->repull_listing_id) {
                $old->forceFill(['source_channel' => null, 'source_listing_id' => null, 'source_photo_id' => null])->saveQuietly();
            }
            Storage::disk('public')->delete([$old->path, $old->thumbnail_path]);
            $old->delete();
        }

        $driver = extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class;
        $manager = new ImageManager(new $driver);

        foreach (array_slice($photos, 0, 30) as $index => $photo) {
            $url = is_array($photo) ? (string) ($photo['url'] ?? $photo['originalUrl'] ?? $photo['imageUrl'] ?? $photo['urlOriginal'] ?? '') : (string) $photo;
            if (! $this->isAllowedPhotoUrl($url)) {
                continue;
            }

            try {
                $response = Http::timeout(25)->withOptions(['allow_redirects' => false])->get($url);
                if (! $response->successful() || strlen($response->body()) > 15 * 1024 * 1024
                    || ! str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/')) {
                    continue;
                }

                $image = $manager->read($response->body());
                $large = $image->scaleDown(width: 1800, height: 1200);
                $thumbnail = $manager->read($response->body())->scaleDown(width: 600, height: 400);
                $base = 'direct-listings/'.$property->id.'/airbnb-'.Str::uuid();
                $path = $base.'.webp';
                $thumbPath = $base.'-thumb.webp';
                Storage::disk('public')->put($path, (string) $large->toWebp(82));
                Storage::disk('public')->put($thumbPath, (string) $thumbnail->toWebp(78));
                MediaService::register($path, 'Airbnb imported photo '.($index + 1), Storage::disk('public')->size($path), 'Direct Listing Photos');

                ListingPhoto::create([
                    'listing_id' => $listing->id,
                    'path' => $path,
                    'thumbnail_path' => $thumbPath,
                    'alt' => (string) ($photo['caption'] ?? ''),
                    'sort_order' => $index,
                    'width' => $large->width(),
                    'height' => $large->height(),
                    'source_channel' => 'airbnb',
                    'source_listing_id' => $listing->repull_listing_id,
                    'source_photo_id' => $photo['photoAirbnbId'] ?? $photo['photo_id'] ?? null,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function isAllowedPhotoUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'muscache.com'
            || str_ends_with($host, '.muscache.com')
            || $host === 'cdn.repull.dev'
            || str_ends_with($host, '.repull.dev');
    }

    private function availableSlug(string $title, Property $property): string
    {
        $base = Str::slug($title ?: $property->name);
        $base = $base !== '' ? $base : 'listing-'.$property->id;
        $candidate = Str::limit($base, 160, '');
        $suffix = 2;

        while (PropertyListing::query()->where('listing_slug', $candidate)->exists()) {
            $tail = '-'.$suffix++;
            $candidate = Str::limit($base, 160 - strlen($tail), '').$tail;
        }

        return $candidate;
    }

    private function enum(?string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function time(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/\A\d{2}:\d{2}(?::\d{2})?\z/', $value)) {
            return null;
        }
        return substr($value, 0, 5);
    }
}
