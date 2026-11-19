<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Pms\ChannexProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ChannexConnectionController extends Controller
{
    public function createProperty(Property $property, ChannexProvider $channex)
    {
        if (! filled($property->country)) {
            return back()->with('error', 'Add the property country code in property settings before creating it in Channex.');
        }

        if (! config('services.channex.api_key')) {
            return back()->with('error', 'Channex API key is not configured on the server.');
        }

        $connection = $channex->verifyConnection();
        if (! $connection['ok']) {
            return back()->with('error', 'Channex connection failed. Check the server API key and '.$connection['environment'].' environment.');
        }

        try {
            if (! $property->channex_property_id) {
                $result = $channex->createProperty([
                    'title' => $property->name,
                    'currency' => config('services.channex.default_currency', 'USD'),
                    'email' => $property->contact_email,
                    'phone' => $property->contact_phone,
                    'address' => $property->address,
                    'city' => $property->city,
                    'state' => $property->state,
                    'zip_code' => $property->zip,
                    'country' => strtoupper((string) $property->country),
                    'latitude' => $property->latitude !== null ? (string) $property->latitude : null,
                    'longitude' => $property->longitude !== null ? (string) $property->longitude : null,
                    'timezone' => $property->timezone ?: config('app.timezone', 'UTC'),
                    'property_type' => 'apartment',
                ], 'guesthub:'.$property->id);

                $externalId = $result['data']['id'] ?? $result['data']['attributes']['id'] ?? null;
                if (! $externalId) {
                    throw new RuntimeException('Channex created the property but returned no property ID.');
                }

                $property->update([
                    'channex_property_id' => (string) $externalId,
                    'channex_room_type_id' => null,
                    'channex_rate_plan_id' => null,
                ]);
            }

            if (! $property->channex_room_type_id) {
                $adultSpaces = max(1, (int) ($property->beds ?: 1) * 2);
                $defaultOccupancy = min(2, $adultSpaces);
                $result = $channex->createRoomType([
                    'property_id' => $property->channex_property_id,
                    'title' => $property->name,
                    'count_of_rooms' => 1,
                    'occ_adults' => $adultSpaces,
                    'occ_children' => 0,
                    'occ_infants' => 0,
                    'default_occupancy' => $defaultOccupancy,
                    'room_kind' => 'room',
                ], $property->channex_property_id);

                $roomTypeId = $result['data']['id'] ?? $result['data']['attributes']['id'] ?? null;
                if (! $roomTypeId) {
                    throw new RuntimeException('Channex created the room type but returned no room type ID.');
                }

                $property->update(['channex_room_type_id' => (string) $roomTypeId]);
            }

            if (! $property->channex_rate_plan_id) {
                $occupancy = min(2, max(1, (int) ($property->beds ?: 1) * 2));
                $result = $channex->createRatePlan([
                    'title' => 'Best Available Rate',
                    'property_id' => $property->channex_property_id,
                    'room_type_id' => $property->channex_room_type_id,
                    'currency' => config('services.channex.default_currency', 'USD'),
                    'sell_mode' => 'per_room',
                    'rate_mode' => 'manual',
                    'options' => [[
                        'occupancy' => $occupancy,
                        'is_primary' => true,
                        'rate' => 0,
                    ]],
                ], $property->channex_property_id);

                $ratePlanId = $result['data']['id'] ?? $result['data']['attributes']['id'] ?? null;
                if (! $ratePlanId) {
                    throw new RuntimeException('Channex created the rate plan but returned no rate plan ID.');
                }

                $property->update(['channex_rate_plan_id' => (string) $ratePlanId]);
            }
        } catch (Throwable $exception) {
            Log::warning('Channex property setup did not complete.', [
                'property_id' => $property->id,
                'channex_property_id' => $property->fresh()->channex_property_id,
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'Channex setup is incomplete. Any IDs already created have been saved; retry this action to continue without duplicating them.');
        }

        return back()->with('success', 'Channex property, room type, and default rate plan are ready. Connect your channels next.');
    }

    public function channels(Request $request, Property $property, ChannexProvider $channex)
    {
        abort_unless($request->user()?->hasRole('admin') || (int) $property->owner_id === (int) $request->user()?->id, 403);
        abort_unless($property->channex_property_id, 422, 'Create the Channex property before connecting channels.');
        if (! filled($property->country) || ! filled($property->contact_email) || ! filled($property->contact_phone)) {
            return redirect()->route('admin.properties.availability.index', $property)
                ->with('error', 'Add the property country code, contact email, and contact phone before connecting OTA channels.');
        }

        try {
            $token = $channex->createOneTimeToken($property->channex_property_id, (string) $request->user()->name);
        } catch (Throwable $exception) {
            Log::warning('Could not create Channex channel iframe token.', [
                'property_id' => $property->id,
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'Channex could not open channel management. Please try again.');
        }

        $iframeUrl = $channex->baseServerUrl().'/auth/exchange?'.http_build_query([
            'token' => $token,
            'redirect_to' => '/channels',
        ]);

        return response()
            ->view('admin.properties.channex-iframe', compact('property', 'iframeUrl'))
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
