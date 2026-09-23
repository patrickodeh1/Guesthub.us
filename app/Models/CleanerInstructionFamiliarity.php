<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleanerInstructionFamiliarity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'instructional_video_id',
        'views_completed',
        'last_viewed_at',
        'last_reset_at',
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
        'last_reset_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function video()
    {
        return $this->belongsTo(InstructionalVideo::class, 'instructional_video_id');
    }
}
