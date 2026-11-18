<?php

namespace App\Services\Pms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WheelhouseApiClient
{
    private const PER_PAGE = 100;

    /**
     * Return every listing visible to this host's Wheelhouse account.
     * The RM API returns a top-level array and uses page/per_page pagination.
     */
    public function listings(string $key, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, min(self::PER_PAGE, $perPage));
        $all = [];
        $page = 1;

        do {
            $items = $this->request('/listings', $key, [
                'per_page' => $perPage,
                'page' => $page,
            ]);
            if (! array_is_list($items)) {
                throw new WheelhouseApiException('Wheelhouse returned an invalid listings response.');
            }

            $all = array_merge($all, array_values(array_filter($items, 'is_array')));
            $count = count($items);
            $page++;
        } while ($count === $perPage);

        return $all;
    }

    public function recommendations(string $listingId, string $key, string $channel): array
    {
        if ($channel === '') {
            throw new \InvalidArgumentException('A Wheelhouse listing channel is required.');
        }

        return $this->request(
            '/listings/'.rawurlencode($listingId).'/price_recommendations',
            $key,
            ['channel' => $channel, 'attribution' => 'false'],
        );
    }

    private function request(string $path, string $key, array $query = []): array
    {
        if (trim($key) === '') {
            throw new WheelhouseApiException('Add your Wheelhouse API key.', 401);
        }

        $url = rtrim((string) config('services.wheelhouse.base_url'), '/').'/'.ltrim($path, '/');
        $response = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['X-Integration-Api-Key' => $key])
                    ->timeout(30)
                    ->get($url, $query);
            } catch (ConnectionException $exception) {
                if ($attempt === 3) {
                    throw new WheelhouseApiException('Wheelhouse could not be reached after several attempts.');
                }

                usleep((int) (1000000 * (2 ** ($attempt - 1))));
                continue;
            }

            $status = $response->status();
            if ($response->successful()) {
                break;
            }

            $retryable = $status === 429 || in_array($status, [500, 502, 503, 504], true);
            if (! $retryable || $attempt === 3) {
                $message = match ($status) {
                    401, 403 => 'That Wheelhouse key was not accepted. Check that it is active and try again.',
                    429 => 'Wheelhouse is rate-limiting requests. Please try again shortly.',
                    default => 'Wheelhouse returned HTTP '.$status.'. Please try again later.',
                };

                throw new WheelhouseApiException($message, $status);
            }

            $retryAfter = (int) $response->header('Retry-After', 0);
            $delaySeconds = $retryAfter > 0 ? min(60, $retryAfter) : (2 ** ($attempt - 1));
            usleep($delaySeconds * 1000000);
        }

        if (! $response || ! $response->successful()) {
            throw new WheelhouseApiException('Wheelhouse request failed after retries.', $response?->status() ?? 0);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
