<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'room_id',
        'task_id',
        'user_id',
        'checked',
        'instruction_viewed',
        'instruction_viewed_at',
        'quantity',
        'note',
        'checked_at'
    ];
    protected $casts = [
        'checked' => 'bool', 
        'checked_at' => 'datetime',
        'instruction_viewed' => 'bool',
        'instruction_viewed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CleaningSession::class, 'session_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ChecklistItemPhoto::class);
    }

    public function hasComment(): bool
    {
        return !empty(trim((string) $this->note));
    }

    public function hasImages(): bool
    {
        return $this->photos->isNotEmpty();
    }

    public function isVerificationItem(): bool
    {
        return $this->task?->type === 'verify';
    }

    public function isIncomplete(): bool
    {
        return $this->checked === false;
    }

    public function isComplete(): bool
    {
        return $this->checked === true;
    }

    /**
     * An item is an "Issue" if it has a non-empty note OR at least one photo.
     * Unchecked status alone does NOT make a task an issue.
     */
    public function isIssue(): bool
    {
        return $this->hasComment() || $this->hasImages();
    }

    public function isActionAlert(): bool
    {
        return $this->isComplete() && ($this->hasComment() || $this->hasImages());
    }

    /**
     * Whether this item's task is instruction-only (should be excluded from
     * completion/compliance calculations).
     */
    public function isInstructionTask(): bool
    {
        return $this->task?->type === 'instructions';
    }
}
