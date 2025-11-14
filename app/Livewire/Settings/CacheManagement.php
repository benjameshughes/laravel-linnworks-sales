<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Events\CacheCleared;
use App\Events\OrdersSynced;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CacheManagement extends Component
{
    public bool $isWarming = false;

    public bool $isClearing = false;

    public ?string $currentlyWarmingPeriod = null;

    public function mount(): void
    {
        // Check authorization
        if (! auth()->user()->can('manage-cache')) {
            abort(403);
        }

        // Check if cache warming is already in progress
        $batch = $this->activeBatch();
        if ($batch && ! $batch['finished']) {
            $this->isWarming = true;

            // Try to determine which period is currently warming
            // Check which cache keys don't exist yet - those are still warming
            $periods = collect(\App\Enums\Period::cacheable())
                ->map(fn ($p) => "{$p->value}d");

            foreach ($periods as $period) {
                $key = "metrics_{$period}_all";
                if (! Cache::has($key)) {
                    $this->currentlyWarmingPeriod = $period;
                    break; // First missing period is likely the current one
                }
            }
        }
    }

    #[Computed]
    public function cacheStatus(): array
    {
        $periods = \App\Enums\Period::cacheable();
        $status = [];

        foreach ($periods as $period) {
            $key = $period->cacheKey('all');
            $cached = Cache::get($key);

            $status["{$period->value}d"] = [
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
        return DB::table('jobs')->where('queue', 'low')->count();
    }

    #[Computed]
    public function activeBatch(): ?array
    {
        // Get the most recent cache warming batch
        $batch = DB::table('job_batches')
            ->where('name', 'warm-metrics-cache')
            ->orderByDesc('created_at')
            ->first();

        if (! $batch) {
            return null;
        }

        // Determine which period is currently being processed
        $currentPeriod = null;
        if (! $batch->finished_at) {
            $periods = \App\Enums\Period::cacheable();
            $cacheStatus = $this->cacheStatus;

            foreach ($periods as $period) {
                $periodKey = "{$period->value}d";
                if (! ($cacheStatus[$periodKey]['exists'] ?? false)) {
                    $currentPeriod = $periodKey;
                    break; // First period without cache is currently warming
                }
            }
        }

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'total_jobs' => $batch->total_jobs,
            'pending_jobs' => $batch->pending_jobs,
            'failed_jobs' => $batch->failed_jobs,
            'processed_jobs' => $batch->total_jobs - $batch->pending_jobs,
            'progress' => $batch->total_jobs > 0
                ? round((($batch->total_jobs - $batch->pending_jobs) / $batch->total_jobs) * 100)
                : 0,
            'finished' => $batch->finished_at !== null,
            'created_at' => $batch->created_at,
            'finished_at' => $batch->finished_at,
            'current_period' => $currentPeriod,
        ];
    }

    #[Computed]
    public function recentCacheWarming(): array
    {
        // No idea why I was loading the laravel log file.
        // Return an empty array for now. Eventually fully remove this
        return [];
    }

    public function warmCache(): void
    {
        $this->isWarming = true;
        $this->currentlyWarmingPeriod = null;

        // Dispatch the OrdersSynced event to trigger cache warming
        OrdersSynced::dispatch(0, 'manual_warm');

        $this->dispatch('cache-warming-triggered');
    }

    public function clearCache(): void
    {
        $this->isClearing = true;

        $periods = \App\Enums\Period::cacheable();

        foreach ($periods as $period) {
            Cache::forget($period->cacheKey('all'));
        }

        // Broadcast cache cleared event
        CacheCleared::dispatch();

        $this->dispatch('cache-cleared');
    }

    #[On('echo:cache-management,CacheWarmingStarted')]
    public function handleWarmingStarted(array $data): void
    {
        $this->isWarming = true;
        $this->currentlyWarmingPeriod = null;
        unset($this->activeBatch);
    }

    #[On('echo:cache-management,CachePeriodWarmingStarted')]
    public function handlePeriodWarmingStarted(array $data): void
    {
        $this->currentlyWarmingPeriod = $data['period'];
        unset($this->activeBatch);
    }

    #[On('echo:cache-management,CachePeriodWarmed')]
    public function handlePeriodWarmed(array $data): void
    {
        // Period finished warming - clear the currently warming state
        $this->currentlyWarmingPeriod = null;

        // Refresh cache status to pick up the newly written cache
        unset($this->cacheStatus);
        unset($this->activeBatch);
        unset($this->recentCacheWarming);

        // Force Livewire to re-render to show updated cache status
        $this->dispatch('$refresh');
    }

    #[On('echo:cache-management,CacheWarmingCompleted')]
    public function handleWarmingCompleted(array $data): void
    {
        $this->isWarming = false;
        $this->currentlyWarmingPeriod = null;

        // Refresh all computed properties
        unset($this->cacheStatus);
        unset($this->activeBatch);
        unset($this->recentCacheWarming);

        // Force Livewire to re-render the component
        $this->dispatch('$refresh');
    }

    #[On('echo:cache-management,CacheCleared')]
    public function handleCacheCleared(array $data): void
    {
        $this->isClearing = false;
        unset($this->cacheStatus); // Reset computed property

        // Force Livewire to re-render to show cache is now cold
        $this->dispatch('$refresh');
    }

    public function render()
    {
        return view('livewire.settings.cache-management');
    }
}
