<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class InstructionalVideo extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'video_file_path',
        'thumbnail_path',
        'duration_seconds',
        'category',
        'is_published',
        'created_by',
        'is_pre_arrival',
        'is_required_before_start',
        'is_required_during_task',
        'required_views',
        'pre_arrival_display_order',
        'training_frequency',
        'completion_threshold_percent',
        'instruction_completion_method',
        'training_version',
        'processing_status'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'duration_seconds' => 'integer',
        'is_pre_arrival' => 'boolean',
        'is_required_before_start' => 'boolean',
        'is_required_during_task' => 'boolean',
        'required_views' => 'integer',
        'pre_arrival_display_order' => 'integer',
        'completion_threshold_percent' => 'integer',
        'training_version' => 'integer',
    ];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'instructional_video_task')
            ->withTimestamps();
    }

    protected $appends = ['video_url', 'thumbnail_url'];

    /**
     * Get properties assigned to this video.
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_instructional_videos', 'instructional_video_id', 'property_id')
            ->withTimestamps();
    }

    /**
     * Get the creator of the video.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include published videos.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Get fully-qualified URL for the video file.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::get(function () {
            $value = $this->video_file_path;
            if (!$value) return null;

            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }

            $path = str_replace('\\', '/', trim((string) $value));
            $path = ltrim($path, '/');
            if (Str::startsWith($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }

            return url('file/' . $path);
        });
    }

    /**
     * Get fully-qualified URL for the thumbnail.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(function () {
            $value = $this->thumbnail_path;
            if (!$value) {
                return asset('images/placeholders/video-placeholder.png');
            }

            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }

            $path = str_replace('\\', '/', trim((string) $value));
            $path = ltrim($path, '/');
            if (Str::startsWith($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }

            return url('file/' . $path);
        });
    }
}
