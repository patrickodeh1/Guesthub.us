<?php

namespace App\Console\Commands;

use App\Mail\InventoryLowStockMail;
use App\Models\InventoryItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInventoryLowStockAlerts extends Command
{
    protected $signature = 'inventory:send-low-stock-alerts';

    protected $description = 'Email property owners when inventory reaches its reorder level.';

    public function handle(): int
    {
        $sent = 0;
        $recovered = 0;
        $failed = 0;

        InventoryItem::query()
            ->where('active', true)
            ->where('reorder_level', '>', 0)
            ->with(['property.owner'])
            ->withSum('stocks', 'quantity')
            ->chunkById(100, function ($items) use (&$sent, &$recovered, &$failed) {
                foreach ($items as $item) {
                    $isLow = $item->quantity_on_hand <= (float) $item->reorder_level;

                    if (! $isLow) {
                        if ($item->low_stock_alerted_at) {
                            $item->forceFill(['low_stock_alerted_at' => null])->save();
                            $recovered++;
                        }
                        continue;
                    }

                    if ($item->low_stock_alerted_at) {
                        continue;
                    }

                    $owner = $item->property?->owner;
                    if (! $owner || ! $owner->email) {
                        $this->warn("Skipped item {$item->id}: property owner or email is missing.");
                        continue;
                    }

                    try {
                        Mail::to($owner->email)->send(new InventoryLowStockMail($item));
                        $item->forceFill(['low_stock_alerted_at' => now()])->save();
                        $sent++;
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        $this->error("Could not send low-stock alert for inventory item {$item->id}.");
                    }
                }
            });

        $this->info("Low-stock alerts sent: {$sent}; recovered items reset: {$recovered}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
