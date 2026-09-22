<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'address',
        'photo_path',
        'beds',
        'baths',
        'latitude',
        'longitude',
        'geo_radius_m',
        'ical_url',
        'airbnb_ical_url',
        'vrbo_ical_url',
        'timezone',
        'is_active',
        'deactivated_at',
        'deactivated_by',
        'notify_cleaning_started',
        'notify_cleaning_finished',
        'notify_photo_started',
        'notify_task_notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
        'notify_cleaning_started' => 'boolean',
        'notify_cleaning_finished' => 'boolean',
        'notify_photo_started' => 'boolean',
        'notify_task_notes' => 'boolean',
    ];

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'property_room')
            ->withTimestamps()
            ->withPivot(['sort_order'])
            ->orderBy('property_room.sort_order');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id', 'id');
    }

    public function propertyTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'property_tasks')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->orderBy('property_tasks.sort_order');
    }

    // Users assigned to this property
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'property_user');
    }

    // Instructional videos assigned to this property
    public function instructionalVideos(): BelongsToMany
    {
        return $this->belongsToMany(InstructionalVideo::class, 'property_instructional_videos', 'property_id', 'instructional_video_id')
            ->withTimestamps();
    }

    // Notification recipients for this property
    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(PropertyNotificationRecipient::class);
    }

    // Notification logs for this property
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function getPhotoUrlAttribute(): string
    {
        if (!$this->photo_path) {
            return asset('images/placeholders/property.png');
        }

        if (str_starts_with($this->photo_path, 'http')) {
            return $this->photo_path;
        }

        $path = str_replace('\\', '/', trim((string) $this->photo_path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return url('file/' . $path);
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    public function scopeWithInactive(Builder $query): void
    {
        // No filter applied, returns all properties
    }

    public function deactivate(User $user): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $user->id,
        ]);
    }

    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
