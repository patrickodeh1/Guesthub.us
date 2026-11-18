<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Property;
use App\Services\Pms\PriceLabs\PriceLabsIapiSync;
use App\Services\Pms\PriceLabs\PriceLabsSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PriceLabsIapiController extends Controller
{
    public function sync(Request $request, PriceLabsSignature $signature, PriceLabsIapiSync $sync): JsonResponse
    {
        if ($this->isVerificationProbe($request)) {
            return response()->json(['ok' => true]);
        }

        if (! $this->validSignature($request, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $listingId = (string) ($payload['listing_id'] ?? '');
        $rows = $payload['data'] ?? null;

        if ($listingId === '' || ! is_array($rows)) {
            return response()->json(['error' => 'Invalid PriceLabs sync payload.'], 422);
        }

        $mappedProperty = Property::query()->where('pricelabs_iapi_listing_id', $listingId)->first();
        if ($mappedProperty
            && ($mappedProperty->pricelabs_mode !== 'pms' || $mappedProperty->pricing_provider !== 'pricelabs')) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        try {
            $rates = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rates[] = [
                    'date' => $row['date'] ?? null,
                    'rate' => $row['price'] ?? null,
                    'min_stay' => $row['min_stay'] ?? null,
                ];
            }

            $result = $sync->applyForListingId($listingId, $rates);

            return response()->json(['ok' => true, 'result' => $result]);
        } catch (Throwable $exception) {
            Property::query()
                ->where('pricelabs_iapi_listing_id', $listingId)
                ->where('pricelabs_mode', 'pms')
                ->update([
                    'pricelabs_iapi_last_error' => mb_substr($exception->getMessage(), 0, 5000),
                    'updated_at' => now(),
                ]);

            Log::warning('PriceLabs IAPI sync callback failed.', [
                'listing_id' => $listingId,
                'exception' => get_class($exception),
            ]);

            return response()->json(['error' => 'Unable to apply PriceLabs rates.'], 422);
        }
    }

    public function calendarTrigger(Request $request, PriceLabsSignature $signature, PriceLabsIapiSync $sync): JsonResponse
    {
        if ($this->isVerificationProbe($request)) {
            return response()->json(['ok' => true]);
        }

        if (! $this->validSignature($request, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $listingId = (string) ($payload['listing_id'] ?? '');
        if ($listingId === '') {
            return response()->json(['error' => 'A listing_id is required.'], 422);
        }

        $mappedProperty = Property::query()->where('pricelabs_iapi_listing_id', $listingId)->first();
        if ($mappedProperty
            && ($mappedProperty->pricelabs_mode !== 'pms' || $mappedProperty->pricing_provider !== 'pricelabs')) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        try {
            $sync->pushCalendarForListingId(
                $listingId,
                isset($payload['start_date']) ? (string) $payload['start_date'] : null,
                isset($payload['end_date']) ? (string) $payload['end_date'] : null,
            );

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            Property::query()
                ->where('pricelabs_iapi_listing_id', $listingId)
                ->where('pricelabs_mode', 'pms')
                ->update([
                    'pricelabs_iapi_last_error' => mb_substr($exception->getMessage(), 0, 5000),
                    'updated_at' => now(),
                ]);

            Log::warning('PriceLabs IAPI calendar trigger failed.', [
                'listing_id' => $listingId,
                'exception' => get_class($exception),
            ]);

            return response()->json(['error' => 'Unable to refresh the requested calendar.'], 422);
        }
    }

    public function hook(Request $request, PriceLabsSignature $signature): JsonResponse
    {
        if ($this->isVerificationProbe($request)) {
            return response()->json(['ok' => true]);
        }

        if (! $this->validSignature($request, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $listingId = data_get($payload, 'listing_id');
        $message = data_get($payload, 'message')
            ?? data_get($payload, 'error')
            ?? data_get($payload, '_json.API_FAILED_SYNC')
            ?? 'PriceLabs sent a notification.';

        $property = $listingId
            ? Property::query()
                ->where('pricelabs_iapi_listing_id', (string) $listingId)
                ->where('pricelabs_mode', 'pms')
                ->first()
            : null;

        if ($property) {
            $property->forceFill([
                'pricelabs_iapi_last_error' => mb_substr((string) $message, 0, 5000),
            ])->save();
        }

        ActivityLog::record(
            'pricelabs_iapi_notification',
            ($property ? $property->name.': ' : 'PriceLabs: ').mb_substr((string) $message, 0, 500),
            'properties',
            $property
        );

        Log::notice('PriceLabs IAPI notification received.', [
            'listing_id' => $listingId,
            'message' => mb_substr((string) $message, 0, 500),
        ]);

        return response()->json(['ok' => true]);
    }

    private function isVerificationProbe(Request $request): bool
    {
        return $request->json()->all() === ['verify' => true]
            && ! $request->headers->has('X-PL-SIGNED-HEADERS')
            && ! $request->headers->has('X-PL-SIGNED-BODY');
    }

    private function validSignature(Request $request, PriceLabsSignature $signature): bool
    {
        $token = (string) config('services.pricelabs.integration_token');

        return $token !== '' && $signature->verify($request, $token);
    }
}
