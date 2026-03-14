<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ShippingSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key. Returns null if not set.
     * Uses short-lived cache to avoid repeated DB hits in same request.
     */
    public static function getValue(string $key): ?string
    {
        $cacheKey = 'shipping_setting:' . $key;

        return Cache::remember($cacheKey, 60, function () use ($key) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value;
        });
    }

    /**
     * Set a setting value by key. Creates or updates the row.
     */
    public static function setValue(string $key, ?string $value): void
    {
        $existing = static::query()->where('key', $key)->first();
        $now = now();
        if ($existing) {
            $existing->update(['value' => $value, 'updated_at' => $now]);
        } else {
            static::query()->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        Cache::forget('shipping_setting:' . $key);
    }

    /**
     * Get all settings as key => value map. Useful for admin form and snapshots.
     */
    public static function getAllAsMap(): array
    {
        return static::query()->pluck('value', 'key')->toArray();
    }

    /**
     * Clear cache for all shipping settings (e.g. after bulk update).
     */
    public static function clearCache(): void
    {
        $keys = static::query()->pluck('key');
        foreach ($keys as $key) {
            Cache::forget('shipping_setting:' . $key);
        }
    }
}
