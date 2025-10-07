<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use App\Jobs\RefreshMetricsCacheJob;
use App\Services\Metrics\SalesMetrics;
use App\Services\Metrics\ProductsMetrics;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CacheStatus extends Component
{
    public bool $showDetails = false;

    public function toggleDetails(): void
    {
        $this->showDetails = !$this->showDetails;
    }

    public function refreshCache(): void
    {
        RefreshMetricsCacheJob::dispatchConcurrent();
        
        $this->dispatch('notification', [
            'message' => 'Cache refresh jobs dispatched successfully!',
            'type' => 'success'
        ]);
    }

    #[Computed]
    public function cacheStatus(): array
    {
        return RefreshMetricsCacheJob::getCacheRefreshStatus();
    }

    #[Computed] 
    public function salesCacheStats(): array
    {
        // Create dummy metrics to get cache stats
        $salesMetrics = new SalesMetrics(collect());
        return $salesMetrics->getCacheStats();
    }

    #[Computed]
    public function productsCacheStats(): array
    {
        // Create dummy metrics to get cache stats  
        $productsMetrics = new ProductsMetrics(collect());
        return $productsMetrics->getCacheStats();
    }

    public function render()
    {
        return view('livewire.components.cache-status');
    }
}