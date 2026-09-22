<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'property_id',
        'cleaning_session_id',
        'task_id',
        'instructional_video_id',
        'training_version',
        'status',
        'progress',
        'views_completed',
        'completed_at',
    ];

    protected $casts = [
        'training_version' => 'integer',
        'progress' => 'integer',
        'views_completed' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function cleaningSession(): BelongsTo
    {
        return $this->belongsTo(CleaningSession::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(InstructionalVideo::class, 'instructional_video_id');
    }
}
