<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskMedia extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'type', 'url', 'thumbnail', 'caption', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = ['url', 'thumbnail'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
    public function getStoragePath(): ?string
    {
        $raw = $this->getRawOriginal('url');
        if (!$raw) return null;
        return self::normalizePath($raw);
    }

    public static function normalizePath(?string $value): ?string
    {
        if (!$value) return $value;

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $parsed = parse_url($value, PHP_URL_PATH);
            $path = ltrim($parsed ?? '', '/');
            if (Str::startsWith($path, 'file/')) {
                return substr($path, strlen('file/'));
            }
            if (Str::startsWith($path, 'storage/')) {
                return substr($path, strlen('storage/'));
            }
            return $path;
        }

        $path = str_replace('\\', '/', trim((string) $value));
        $path = ltrim($path, '/');
        if (Str::startsWith($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        return $path;
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) return $value;

                if (Str::startsWith($value, ['http://', 'https://'])) {
                    return $value;
                }

                $path = self::normalizePath($value);
                return url('file/' . $path);
            },
            set: function ($value) {
                return self::normalizePath($value);
            }
        );
    }

    /**
     * Same treatment for thumbnail.
     */
    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) return $value;

                if (Str::startsWith($value, ['http://', 'https://'])) {
                    return $value;
                }

                $path = self::normalizePath($value);
                return url('file/' . $path);
            },
            set: function ($value) {
                return self::normalizePath($value);
            }
        );
    }
}
