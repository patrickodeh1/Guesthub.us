<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructionStep extends Model
{
    protected $fillable = [
        'property_id', 'source_step_id', 'type', 'action', 'sort_order', 'title', 'content', 'image_path', 'active', 'visibility',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InstructionStepImage::class)->orderBy('sort_order');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? url('/img/' . $this->image_path) : null;
    }

    public function renderContent(Booking $booking): string
    {
        if (!$this->content) return '';

        return str_replace(
            [
                '[[guest_name]]',
                '[[guest_first_name]]',
                '[[guest_last_name]]',
                '[[guest_phone]]',
                '[[booking_id]]',
                '[[check_in_date]]',
                '[[check_out_date]]',
                '[[property_name]]',
                '[[property_address]]',
                '[[lockbox_code]]',
            ],
            [
                $booking->guest_name,
                str($booking->guest_name)->before(' ')->toString(),
                str($booking->guest_name)->after(' ')->toString(),
                $booking->phone,
                $booking->booking_id,
                $booking->check_in_date->format('M d, Y'),
                $booking->check_out_date->format('M d, Y'),
                $booking->property->name,
                $booking->property->address,
                $booking->property->lockbox_code ?? '',
            ],
            $this->content
        );
    }
}
