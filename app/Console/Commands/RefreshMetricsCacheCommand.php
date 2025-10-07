<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RefreshMetricsCacheJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshMetricsCacheCommand extends Command
{
    protected $signature = 'metrics:refresh-cache 
                            {--period=30 : Time period for metrics (1, yesterday, 7, 30, 90)}
                            {--channel= : Specific channel to refresh (optional)}
                            {--concurrent : Dispatch concurrent jobs for all periods}
                            {--status : Show cache refresh status}';

    protected $description = 'Refresh metrics cache to keep the application responsive';

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if ($this->option('concurrent')) {
            return $this->dispatchConcurrentJobs();
        }

        return $this->refreshSinglePeriod();
    }

    private function showStatus(): int
    {
        $status = RefreshMetricsCacheJob::getCacheRefreshStatus();
        
        $this->info('Metrics Cache Refresh Status');
        $this->line('================================');
        
        if ($status['last_refresh']) {
            $this->info('✅ Last Successful Refresh:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Timestamp', $status['last_refresh']['timestamp']],
                    ['Period', $status['last_refresh']['period']],
                    ['Channel', $status['last_refresh']['channel'] ?? 'all'],
                    ['Orders Count', number_format($status['last_refresh']['orders_count'])],
                    ['Duration', $status['last_refresh']['duration_ms'] . 'ms'],
                ]
            );
        } else {
            $this->warn('❌ No successful refresh found');
        }

        if ($status['last_failure']) {
            $this->error('❌ Last Failure:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Timestamp', $status['last_failure']['timestamp']],
                    ['Period', $status['last_failure']['period']],
                    ['Channel', $status['last_failure']['channel'] ?? 'all'],
                    ['Error', $status['last_failure']['error']],
                    ['Attempts', $status['last_failure']['attempts']],
                ]
            );
        }

        $healthStatus = $status['is_healthy'] ? '✅ Healthy' : '❌ Unhealthy';
        $this->line('');
        $this->info("Overall Status: {$healthStatus}");

        return 0;
    }

    private function dispatchConcurrentJobs(): int
    {
        $this->info('🚀 Dispatching concurrent metrics cache refresh jobs...');
        
        RefreshMetricsCacheJob::dispatchConcurrent();
        
        $this->info('✅ All cache refresh jobs have been dispatched!');
        $this->line('Jobs will run concurrently to warm up the cache for all periods.');
        $this->line('Use --status to check the refresh status.');
        
        return 0;
    }

    private function refreshSinglePeriod(): int
    {
        $period = $this->option('period');
        $channel = $this->option('channel');
        
        $this->info("🔄 Refreshing metrics cache for period: {$period}" . 
                   ($channel ? ", channel: {$channel}" : ''));
        
        RefreshMetricsCacheJob::dispatch($period, $channel);
        
        $this->info('✅ Cache refresh job dispatched successfully!');
        $this->line('The job will run in the background to warm up the cache.');
        
        return 0;
    }
}