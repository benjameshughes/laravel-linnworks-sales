<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AnalyticsCacheStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:cache-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of analytics cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Product Analytics Cache Status');
        $this->info('==============================');
        
        // Check common cache keys
        $cacheKeys = [
            'stock_alerts' => 'Stock Alerts',
            'product_analytics_metrics_' . md5(serialize([30, null, null])) => 'Default Metrics (30 days)',
            'product_analytics_top_products_' . md5(serialize([30, null, null, 50])) => 'Top Products (30 days)',
            'product_analytics_top_categories_' . md5(serialize([30])) => 'Top Categories (30 days)',
        ];
        
        $cachedCount = 0;
        $totalCount = count($cacheKeys);
        
        foreach ($cacheKeys as $key => $label) {
            if (Cache::has($key)) {
                $this->line("✓ {$label}: <info>Cached</info>");
                $cachedCount++;
            } else {
                $this->line("✗ {$label}: <comment>Not cached</comment>");
            }
        }
        
        $this->newLine();
        $this->info("Cache Coverage: {$cachedCount}/{$totalCount} keys cached");
        
        // Check last sync from logs
        $logPath = storage_path('logs/analytics-cache.log');
        if (file_exists($logPath)) {
            $lastModified = Carbon::createFromTimestamp(filemtime($logPath));
            $this->info('Last cache refresh: ' . $lastModified->diffForHumans());
        } else {
            $this->comment('No cache refresh log found');
        }
        
        // Provide recommendations
        $this->newLine();
        if ($cachedCount < $totalCount) {
            $this->comment('Recommendation: Run "php artisan analytics:refresh-cache" to populate cache');
        } else {
            $this->info('✓ Cache is fully populated and ready for fast dashboard loading!');
        }
        
        return Command::SUCCESS;
    }
}