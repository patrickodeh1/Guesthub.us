<?php

namespace App\Services\Pms;

use App\Models\Property;
use App\Models\User;

class WheelhouseConnectionService
{
    public function __construct(private WheelhouseApiClient $client) {}

    public function connectIntegratorListing(Property $property): void
    {
        $owner = $property->owner;
        if (! $owner) {
            throw new WheelhouseApiException('Assign this property to a host before connecting Wheelhouse.');
        }
        if (! $owner->email) {
            throw new WheelhouseApiException('Add an email address to the host account before connecting Wheelhouse.');
        }
        if ($owner->wheelhouse_user_api_key && ! $owner->wheelhouse_integrators_user_id) {
            throw new WheelhouseApiException('This host already uses a Wheelhouse account. Choose one of its existing listings instead.');
        }

        $integratorId = $owner->wheelhouse_integrators_user_id ?: $this->integratorsUserId($owner);
        if (! $owner->wheelhouse_integrators_user_id) {
            $owner->forceFill(['wheelhouse_integrators_user_id' => $integratorId])->save();
        }

        $userApiKey = $owner->wheelhouse_user_api_key;
        if (! $userApiKey) {
            $user = $this->client->registerUser([
                'first_name' => $this->firstName($owner),
                'last_name' => $this->lastName($owner),
                'email' => $owner->email,
                'integrators_user_id' => $integratorId,
                'phone' => $owner->phone_number ?: $owner->phone,
                'company' => 'GuestHub',
            ]);
            $userApiKey = (string) ($user['user_api_key'] ?? '');
            if ($userApiKey === '') {
                throw new WheelhouseApiException('Wheelhouse did not return a user API key.');
            }
            $owner->forceFill(['wheelhouse_user_api_key' => $userApiKey])->save();
        }

        $listingId = $this->integratorsListingId($owner, $property);
        $this->client->upsertListing($listingId, $this->listingPayload($property, $owner, $integratorId, $listingId), $userApiKey);

        $property->forceFill([
            'wheelhouse_listing_id' => $listingId,
            'wheelhouse_channel' => null,
        ])->save();
    }

    public function integratorsListingId(User $owner, Property $property): string
    {
        $prefix = $this->environmentPrefix();

        return "{$prefix}-{$owner->getKey()}-{$property->getKey()}";
    }

    private function integratorsUserId(User $owner): string
    {
        $prefix = $this->environmentPrefix();

        return "{$prefix}-{$owner->getKey()}";
    }

    private function environmentPrefix(): string
    {
        return in_array(app()->environment(), ['prod', 'production'], true) ? 'prod' : 'test';
    }

    private function firstName(User $owner): string
    {
        $name = trim((string) ($owner->host_name ?: $owner->name));

        return preg_split('/\s+/', $name, 2)[0] ?: 'GuestHub';
    }

    private function lastName(User $owner): string
    {
        $name = trim((string) ($owner->host_name ?: $owner->name));
        $parts = preg_split('/\s+/', $name, 2);

        return trim($parts[1] ?? '') ?: 'Host';
    }

    private function listingPayload(Property $property, User $owner, string $integratorId, string $listingId): array
    {
        $listing = $property->listing;

        return [
            'id' => $listingId,
            'title' => $listing?->public_title ?: $property->name,
            'location' => [
                'address' => $property->fullAddress(),
                'postal_code' => $property->zip,
                'country' => 'US',
                'latitude' => $property->latitude !== null ? (float) $property->latitude : null,
                'longitude' => $property->longitude !== null ? (float) $property->longitude : null,
            ],
            'num_bedrooms' => $listing?->bedrooms,
            'num_beds' => $property->beds,
            'num_bathrooms' => $property->baths !== null ? (float) $property->baths : null,
            'room_type' => 'entire_place',
            'property_type' => 'house',
            'currency' => 'USD',
            'security_deposit' => null,
            'star_rating' => null,
            'num_reviews' => 0,
            'num_photos' => 0,
            'amenities' => [],
            'listing_preferences' => [
                'num_max_guests' => $listing?->max_guests,
                'base_price' => $listing?->base_rate_cents !== null ? $listing->base_rate_cents / 100 : null,
                'minimum_stay' => $listing?->min_nights,
            ],
            'channel_ids' => null,
            'description' => $listing?->public_description,
            'nickname' => $property->name,
            'owner_name' => $owner->name,
            'is_active' => (bool) $property->active,
            'source_user_id' => $integratorId,
            'created_at' => $property->created_at?->toIso8601String(),
        ];
    }
}
