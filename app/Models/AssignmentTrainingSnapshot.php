<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentTrainingSnapshot extends Model
{
    protected $fillable = [
        'cleaning_session_id',
        'task_id',
        'instructional_video_id',
        'required_version',
        'is_required_before_start',
        'display_order',
    ];

    protected $casts = [
        'required_version' => 'integer',
        'is_required_before_start' => 'boolean',
        'display_order' => 'integer',
    ];

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
