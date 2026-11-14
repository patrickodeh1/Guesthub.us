<?php

namespace App\Mail;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InventoryLowStockMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InventoryItem $item) {}

    public function build(): static
    {
        return $this->subject('Low inventory: '.$this->item->name)
            ->markdown('emails.inventory-low-stock');
    }
}
