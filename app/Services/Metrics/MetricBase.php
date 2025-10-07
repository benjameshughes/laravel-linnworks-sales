<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Base metric class for getting and analysing data sets with caching support
 */
abstract class MetricBase
{
    protected Collection $data;
    protected int $cacheMinutes = 15;
    protected string $cachePrefix;

    public function __construct(Collection $data)
    {
        $this->data = $data;
        $this->cachePrefix = strtolower(class_basename(static::class));
    }

    public function getData(): Collection
    {
        return $this->data;
    }

    /**
     * Cache a metric calculation result
     */
    protected function cache(string $key, callable $callback, ?int $minutes = null): mixed
    {
        $minutes = $minutes ?? $this->cacheMinutes;
        $cacheKey = $this->getCacheKey($key);
        
        return Cache::remember($cacheKey, now()->addMinutes($minutes), $callback);
    }

    /**
     * Get cached value or return null if not cached
     */
    protected function getCached(string $key): mixed
    {
        return Cache::get($this->getCacheKey($key));
    }

    /**
     * Store value in cache
     */
    protected function putCache(string $key, mixed $value, ?int $minutes = null): void
    {
        $minutes = $minutes ?? $this->cacheMinutes;
        Cache::put($this->getCacheKey($key), $value, now()->addMinutes($minutes));
    }

    /**
     * Clear specific cache key
     */
    protected function forgetCache(string $key): void
    {
        Cache::forget($this->getCacheKey($key));
    }

    /**
     * Clear all cache for this metric service
     */
    public function clearAllCache(): void
    {
        $pattern = $this->cachePrefix . ':*';
        
        // Get all cache keys matching our pattern
        if (config('cache.default') === 'redis') {
            $keys = Cache::getRedis()->keys(config('cache.prefix') . ':' . $pattern);
            if (!empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        } else {
            // For non-Redis cache stores, we'll track keys manually
            $trackedKeys = Cache::get($this->cachePrefix . ':tracked_keys', []);
            foreach ($trackedKeys as $key) {
                Cache::forget($key);
            }
            Cache::forget($this->cachePrefix . ':tracked_keys');
        }
    }

    /**
     * Generate cache key with data fingerprint
     */
    protected function getCacheKey(string $key): string
    {
        $dataFingerprint = $this->getDataFingerprint();
        $fullKey = "{$this->cachePrefix}:{$key}:{$dataFingerprint}";
        
        // Track this key for non-Redis cache stores
        if (config('cache.default') !== 'redis') {
            $trackedKeys = Cache::get($this->cachePrefix . ':tracked_keys', []);
            $trackedKeys[] = $fullKey;
            Cache::put($this->cachePrefix . ':tracked_keys', array_unique($trackedKeys), now()->addHours(24));
        }
        
        return $fullKey;
    }

    /**
     * Create a fingerprint of the data for cache invalidation
     */
    protected function getDataFingerprint(): string
    {
        if ($this->data->isEmpty()) {
            return 'empty';
        }

        $idsSignature = $this->data
            ->map(function ($item) {
                if (is_object($item) && method_exists($item, 'getKey')) {
                    return $item->getKey();
                }

                if (is_array($item) && array_key_exists('id', $item)) {
                    return $item['id'];
                }

                return md5(json_encode($item));
            })
            ->filter()
            ->sort()
            ->implode('|');

        $latestTimestamp = $this->data
            ->map(function ($item) {
                if (isset($item->updated_at) && $item->updated_at instanceof Carbon) {
                    return $item->updated_at->timestamp;
                }

                if (isset($item->received_date) && $item->received_date instanceof Carbon) {
                    return $item->received_date->timestamp;
                }

                if (is_array($item) && isset($item['received_date'])) {
                    return Carbon::parse($item['received_date'])->timestamp;
                }

                return null;
            })
            ->filter()
            ->max() ?? 0;
        
        return md5($idsSignature . ':' . $latestTimestamp);
    }

    /**
     * Set cache duration in minutes
     */
    public function setCacheDuration(int $minutes): static
    {
        $this->cacheMinutes = $minutes;
        return $this;
    }

    /**
     * Check if cache is warm (has data)
     */
    public function isCacheWarm(): bool
    {
        return !empty($this->getCached('_cache_status'));
    }

    /**
     * Warm up the cache by running all expensive operations
     */
    abstract public function warmUpCache(): void;

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cache_prefix' => $this->cachePrefix,
            'data_fingerprint' => $this->getDataFingerprint(),
            'data_count' => $this->data->count(),
            'cache_duration_minutes' => $this->cacheMinutes,
            'is_cache_warm' => $this->isCacheWarm(),
            'last_warmed' => $this->getCached('_last_warmed'),
        ];
    }
}
