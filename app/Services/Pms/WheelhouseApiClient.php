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
        $response = Http::acceptJson()
            ->withHeaders(['X-Integration-Api-Key' => $key])
            ->timeout(30)
            ->retry(
                [1000, 3000],
                throw: false,
                when: function ($exception, $request) {
                    $status = $exception->response?->status();

                    return $exception instanceof ConnectionException
                        || $status === 429
                        || in_array($status, [500, 502, 503, 504], true);
                },
            )
            ->get($url, $query);

        if (! $response->successful()) {
            $status = $response->status();
            $message = match ($status) {
                401, 403 => 'That Wheelhouse key was not accepted. Check that it is active and try again.',
                429 => 'Wheelhouse is rate-limiting requests. Please try again shortly.',
                default => 'Wheelhouse returned HTTP '.$status.'. Please try again later.',
            };

            throw new WheelhouseApiException($message, $status);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
