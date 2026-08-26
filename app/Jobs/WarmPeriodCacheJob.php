<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use App\Events\CachePeriodWarmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\CachePeriodWarmingStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Metrics\ChunkedMetricsCalculator;

final class WarmPeriodCacheJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 0;

    public int $maxExceptions = 3;

    public function __construct(
        public readonly string $period,
        public readonly string $channel = 'all',
        public readonly string $status = 'all'
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        CachePeriodWarmingStarted::dispatch("{$this->period}d");

        DB::connection()->disableQueryLog();

        $calculator = new ChunkedMetricsCalculator($this->period, $this->channel, $this->status);
        $cacheData = $calculator->calculate();

        $periodEnum = \App\Enums\Period::tryFrom($this->period);
        $cacheKey = $periodEnum?->cacheKey($this->channel, $this->status) ?? "metrics_{$this->period}d_{$this->channel}_{$this->status}";
        Cache::forever($cacheKey, $cacheData);

        Log::debug('Cache warmed', [
            'cache_key' => $cacheKey,
            'orders' => $cacheData['orders'],
        ]);

        CachePeriodWarmed::dispatch(
            "{$this->period}d",
            $cacheData['orders'],
            $cacheData['revenue'],
            $cacheData['items']
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WarmPeriodCacheJob failed', [
            'period' => $this->period,
            'channel' => $this->channel,
            'status' => $this->status,
            'error' => $exception->getMessage(),
        ]);
    }
}
