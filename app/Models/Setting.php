<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            return static::query()->where('key', $key)->value('value') ?? $default;
        });
    }

    public static function putValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }

    // Compatibility aliases for cleaning code that uses Setting::get() and Setting::set().
    public static function get(string $key, $default = null): mixed
    {
        return static::getValue($key, $default);
    }

    public static function set(string $key, $value): void
    {
        static::putValue($key, $value);
    }
}
