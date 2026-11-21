<?php

namespace App\Jobs;

use App\Services\Repull\RepullClient;
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
        $client->deleteListingPhoto($this->listingId, $this->path, 'guesthub-repull-photo-delete-'.hash('sha256', $this->listingId.'|'.$this->path));
    }
}
