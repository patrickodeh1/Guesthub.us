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

    /** True when this cleaner must attach a photo to this task at this property before ticking it off. */
    public function requiresPhotoFrom(int $propertyId, int $userId): bool
    {
        if ($userId <= 0 || $propertyId <= 0 || ! $this->id) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('task_photo_requirements')
            ->where('task_id', $this->id)
            ->where('property_id', $propertyId)
            ->where('user_id', $userId)
            ->exists();
    }

    /** Cleaners who can be asked to attach photos for tasks at this property. */
    public static function photoCleanerOptions($property)
    {
        $q = \App\Models\User::role('housekeeper');
        if (! empty($property->owner_id)) {
            $ids = \Illuminate\Support\Facades\DB::table('housekeeper_owner')
                ->where('owner_id', $property->owner_id)->pluck('housekeeper_id');
            if ($ids->isNotEmpty()) {
                $q->whereIn('users.id', $ids);
            }
        }

        return $q->orderBy('name')->get(['users.id', 'users.name']);
    }

    public function photoRequiredUserIds(int $propertyId): array
    {
        return \Illuminate\Support\Facades\DB::table('task_photo_requirements')
            ->where('task_id', $this->id)->where('property_id', $propertyId)
            ->pluck('user_id')->map(fn ($i) => (int) $i)->all();
    }

    public function syncPhotoRequirements(int $propertyId, array $userIds): void
    {
        $allowed = static::photoCleanerOptions(\App\Models\Property::find($propertyId))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
        $wanted = array_values(array_intersect(array_map('intval', $userIds), $allowed));
        $tbl = \Illuminate\Support\Facades\DB::table('task_photo_requirements')
            ->where('task_id', $this->id)->where('property_id', $propertyId);

        (clone $tbl)->whereNotIn('user_id', $wanted)->delete();
        $existing = (clone $tbl)->pluck('user_id')->map(fn ($i) => (int) $i)->all();
        foreach (array_diff($wanted, $existing) as $uid) {
            \Illuminate\Support\Facades\DB::table('task_photo_requirements')->insert([
                'property_id' => $propertyId, 'task_id' => $this->id, 'user_id' => $uid,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

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
        static::creating(function (self $model) {
            if (auth()->check()
                && empty($model->created_by)
                && \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'created_by')) {
                $model->created_by = auth()->id();
            }
        });

        static::saved(function () {
            Cache::flush();
        });

        static::deleted(function () {
            Cache::flush();
        });
    }
}
