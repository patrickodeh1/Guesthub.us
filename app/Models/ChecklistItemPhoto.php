<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItemPhoto extends Model
{
    use HasFactory;

    protected $table = 'checklist_item_photos';

    protected $fillable = [
        'checklist_item_id',
        'room_id',
        'property_id',
        'path',
        'note',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    protected $appends = ['url'];

    /**
     * Get the checklist item this photo belongs to.
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the URL for this photo.
     */
    public function getUrlAttribute(): string
    {
        $path = str_replace('\\', '/', trim((string) $this->path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? url('file/' . $path) : '';
    }
}
