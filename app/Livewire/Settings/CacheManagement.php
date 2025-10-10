<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Events\OrdersSynced;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class CacheManagement extends Component
{
    public function mount(): void
    {
        // Check authorization
        if (!auth()->user()->can('manage-cache')) {
            abort(403);
        }
    }

    #[Computed]
    public function cacheStatus(): array
    {
        $keys = ['metrics_7d_all', 'metrics_30d_all', 'metrics_90d_all'];
        $status = [];

        foreach ($keys as $key) {
            $cached = Cache::get($key);
            $status[str_replace('metrics_', '', str_replace('_all', '', $key))] = [
                'exists' => $cached !== null,
                'warmed_at' => $cached['warmed_at'] ?? null,
                'revenue' => $cached['revenue'] ?? 0,
                'orders' => $cached['orders'] ?? 0,
                'items' => $cached['items'] ?? 0,
            ];
        }

        return $status;
    }

    #[Computed]
    public function queuedJobs(): int
    {
        return DB::table('jobs')->where('queue', 'default')->count();
    }

    #[Computed]
    public function recentCacheWarming(): array
    {
        // Get last 5 cache warming log entries from Laravel log
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile);
        $warmingLogs = [];

        // Read file backwards to get most recent entries
        for ($i = count($lines) - 1; $i >= 0 && count($warmingLogs) < 5; $i--) {
            if (str_contains($lines[$i], 'Cache warmed successfully')) {
                // Parse log entry
                preg_match('/\[(.*?)\].*cache_key":"(.*?)".*orders_count":(\d+)/', $lines[$i], $matches);
                if (count($matches) === 4) {
                    $warmingLogs[] = [
                        'timestamp' => $matches[1],
                        'cache_key' => $matches[2],
                        'orders_count' => (int) $matches[3],
                    ];
                }
            }
        }

        return $warmingLogs;
    }

    public function warmCache(): void
    {
        // Dispatch the OrdersSynced event to trigger cache warming
        OrdersSynced::dispatch(0, 'manual_warm');

        $this->dispatch('cache-warming-triggered');
        session()->flash('cache-warmed', 'Cache warming has been queued. It will complete in ~30 seconds.');
    }

    public function clearCache(): void
    {
        $keys = ['metrics_7d_all', 'metrics_30d_all', 'metrics_90d_all'];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->dispatch('cache-cleared');
        session()->flash('cache-cleared', 'All dashboard metric caches have been cleared.');
    }

    public function render()
    {
        return view('livewire.settings.cache-management');
    }
}
