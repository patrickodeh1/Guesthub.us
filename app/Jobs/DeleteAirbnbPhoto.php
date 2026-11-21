<?php

namespace App\Jobs;

use App\Services\Repull\RepullClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteAirbnbPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $listingId,
        public string $photoId,
    ) {}

    public function handle(RepullClient $client): void
    {
        $client->deleteAirbnbPhoto($this->listingId, $this->photoId, 'guesthub-airbnb-photo-delete-'.$this->photoId);
    }
}
