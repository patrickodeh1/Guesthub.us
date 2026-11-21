<?php

namespace App\Jobs;

use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteRepullPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $listingId,
        public string $path,
    ) {}

    public function handle(RepullClient $client): void
    {
        try {
            $client->deleteListingPhoto($this->listingId, $this->path);
        } catch (RepullException $e) {
            if ($e->httpStatus === 404 || $e->errorCode === 'not_found') {
                return;
            }
            throw $e;
        }
    }
}
