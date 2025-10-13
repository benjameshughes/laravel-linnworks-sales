<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get a setting value with optional default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $setting = AppSetting::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value
     */
    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $userId,
            ]
        );

        Cache::forget("setting:{$key}");
    }

    /**
     * Get setting as array
     */
    public function getArray(string $key): array
    {
        return (array) $this->get($key, []);
    }

    /**
     * Get setting as boolean
     */
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    /**
     * Get setting as string
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * Get setting as integer
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    /**
     * Check if a setting exists
     */
    public function has(string $key): bool
    {
        return AppSetting::where('key', $key)->exists();
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): bool
    {
        Cache::forget("setting:{$key}");

        return AppSetting::where('key', $key)->delete() > 0;
    }

    /**
     * Get all settings of a specific type
     */
    public function getByType(string $type): array
    {
        return AppSetting::where('type', $type)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value])
            ->toArray();
    }

    /**
     * Clear all settings cache
     */
    public function clearCache(): void
    {
        // This would require knowing all keys, so we'd need to implement
        // a tag-based cache strategy or just clear specific known keys
        Cache::flush();
    }
}
