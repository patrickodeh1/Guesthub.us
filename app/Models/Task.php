<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id', 'room_id', 'name', 'is_default', 'is_sporadic', 'type', 'phase', 'instructions',
        'is_pre_arrival', 'is_required_before_start', 'pre_arrival_display_order',
        'estimated_duration_minutes', 'training_frequency', 'completion_threshold_percent',
        'instruction_completion_method', 'training_version', 'is_required_during_task', 'required_views'
    ]; // type: 'room'|'inventory', phase: 'pre_cleaning'|'during_cleaning'|'post_cleaning'
    
    protected $casts = [
        'is_default' => 'boolean',
        'is_sporadic' => 'boolean',
        'type' => 'string', // 'room' or 'inventory'
        'phase' => 'string', // 'pre_cleaning', 'during_cleaning', 'post_cleaning', or null for room-level tasks
        'is_pre_arrival' => 'boolean',
        'is_required_before_start' => 'boolean',
        'is_required_during_task' => 'boolean',
        'required_views' => 'integer',
        'pre_arrival_display_order' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'completion_threshold_percent' => 'integer',
        'training_version' => 'integer',
    ];

    public function instructionalVideos(): BelongsToMany
    {
        return $this->belongsToMany(InstructionalVideo::class, 'instructional_video_task')
            ->withTimestamps();
    }



    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_task')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper']);
    }

    public function media(): HasMany
    {
        return $this->hasMany(TaskMedia::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_tasks')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->orderBy('property_tasks.sort_order');
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::flush();
        });

        static::deleted(function () {
            Cache::flush();
        });
    }
}
