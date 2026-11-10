<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\Pms\PriceLabsSync;
use Illuminate\Http\Request;

class WheelhouseWebhookController extends Controller
{
    public function handle(Request $request, string $secret, PriceLabsSync $sync)
    {
        $configuredSecret = (string) config('services.wheelhouse.webhook_secret');
        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $secret)) {
            abort(404);
        }

        $event = (string) $request->input('event');
        if (in_array($event, ['account_sync_requested'], true)) {
            $externalUserId = $request->input('external_user_id');
            if (! is_string($externalUserId) || $externalUserId === '') {
                return response()->json(['message' => 'An external_user_id is required for this event.'], 422);
            }
            $properties = Property::query()
                ->where('pricing_provider', 'wheelhouse')
                ->whereNotNull('wheelhouse_listing_id')
                ->whereHas('owner', fn ($owners) => $owners->where('wheelhouse_integrators_user_id', $externalUserId))
                ->get();

            foreach ($properties as $property) {
                $result = $sync->sync($property, true);
                if (in_array($result['status'], ['failed', 'unauthorized'], true)) {
                    return response()->json(['message' => $result['message']], 502);
                }
            }

            return response()->noContent();
        }

        if (! in_array($event, [
            'listing_recommendations_updated',
            'listing_sync_requested',
            'price_updated',
            'calendar_refresh_requested',
        ], true)) {
            return response()->noContent();
        }

        $listingId = (string) $request->input('listing_id');
        if ($listingId === '') {
            return response()->json(['message' => 'A listing_id is required for this event.'], 422);
        }

        $property = Property::query()
            ->where('pricing_provider', 'wheelhouse')
            ->where('wheelhouse_listing_id', $listingId)
            ->whereHas('owner', function ($owners) use ($request) {
                $externalUserId = $request->input('external_user_id');
                $owners->where(function ($query) use ($externalUserId) {
                    $query->whereNull('wheelhouse_integrators_user_id')
                        ->orWhere('wheelhouse_integrators_user_id', $externalUserId);
                });
            })
            ->first();

        if ($property) {
            $result = $sync->sync($property, true);
            if (in_array($result['status'], ['failed', 'unauthorized'], true)) {
                return response()->json(['message' => $result['message']], 502);
            }
        }

        return response()->noContent();
    }
}
