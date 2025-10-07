<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\Metrics\SalesMetrics;
use App\Services\Metrics\ProductsMetrics;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshMetricsCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public int $backoff = 60; // 1 minute

    private string $period;
    private ?string $channel;

    public function __construct(string $period = '30', ?string $channel = null)
    {
        $this->period = $period;
        $this->channel = $channel;
        
        // Use high priority queue for cache refresh
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Starting metrics cache refresh', [
                'period' => $this->period,
                'channel' => $this->channel,
                'job_id' => $this->job->getJobId(),
            ]);

            // Get the orders data for the specified period
            $orders = $this->getOrdersForPeriod();
            
            if ($orders->isEmpty()) {
                Log::warning('No orders found for cache refresh', [
                    'period' => $this->period,
                    'channel' => $this->channel,
                ]);
                return;
            }

            // Warm up sales metrics cache
            $salesMetrics = new SalesMetrics($orders);
            $salesMetrics->warmUpCache();

            // Warm up products metrics cache  
            $productsMetrics = new ProductsMetrics($orders);
            $productsMetrics->warmUpCache();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Metrics cache refresh completed successfully', [
                'period' => $this->period,
                'channel' => $this->channel,
                'orders_count' => $orders->count(),
                'duration_ms' => $duration,
                'sales_cache_stats' => $salesMetrics->getCacheStats(),
                'products_cache_stats' => $productsMetrics->getCacheStats(),
            ]);

            // Store cache refresh metadata
            Cache::put('metrics_cache_last_refresh', [
                'timestamp' => now()->toISOString(),
                'period' => $this->period,
                'channel' => $this->channel,
                'orders_count' => $orders->count(),
                'duration_ms' => $duration,
            ], now()->addHours(24));

        } catch (Throwable $e) {
            Log::error('Metrics cache refresh failed', [
                'period' => $this->period,
                'channel' => $this->channel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    private function getOrdersForPeriod()
    {
        $dateRange = $this->getDateRange();
        
        $query = Order::whereBetween('received_date', [
                $dateRange['start'],
                $dateRange['end']
            ])
            ->where('channel_name', '!=', 'DIRECT');

        if ($this->channel && $this->channel !== 'all') {
            $query->where('channel_name', $this->channel);
        }

        return $query->orderByDesc('received_date')->get();
    }

    private function getDateRange(): array
    {
        if ($this->period === 'yesterday') {
            return [
                'start' => Carbon::yesterday()->startOfDay(),
                'end' => Carbon::yesterday()->endOfDay(),
            ];
        }

        $days = (int) $this->period;

        return [
            'start' => Carbon::now()->subDays($days)->startOfDay(),
            'end' => Carbon::now()->endOfDay(),
        ];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RefreshMetricsCacheJob failed permanently', [
            'period' => $this->period,
            'channel' => $this->channel,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Store failure information
        Cache::put('metrics_cache_last_failure', [
            'timestamp' => now()->toISOString(),
            'period' => $this->period,
            'channel' => $this->channel,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ], now()->addHours(24));
    }

    /**
     * Create concurrent jobs for different periods and channels
     */
    public static function dispatchConcurrent(): void
    {
        $periods = ['1', 'yesterday', '7', '30', '90'];
        $channels = ['all']; // Add specific channels if needed
        
        Log::info('Dispatching concurrent metrics cache refresh jobs', [
            'periods' => $periods,
            'channels' => $channels,
            'total_jobs' => count($periods) * count($channels),
        ]);

        foreach ($periods as $period) {
            foreach ($channels as $channel) {
                static::dispatch($period, $channel === 'all' ? null : $channel)
                    ->delay(now()->addSeconds(rand(1, 30))); // Stagger job execution
            }
        }
    }

    /**
     * Get cache refresh status
     */
    public static function getCacheRefreshStatus(): array
    {
        $lastRefresh = Cache::get('metrics_cache_last_refresh');
        $lastFailure = Cache::get('metrics_cache_last_failure');
        
        return [
            'last_refresh' => $lastRefresh,
            'last_failure' => $lastFailure,
            'is_healthy' => $lastRefresh && 
                           (!$lastFailure || 
                            Carbon::parse($lastRefresh['timestamp'])->isAfter(Carbon::parse($lastFailure['timestamp']))),
        ];
    }
}