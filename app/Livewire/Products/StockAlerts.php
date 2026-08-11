<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

final class StockAlerts extends Component
{
    #[On('products-filters-updated')]
    public function updateFilters(
        string $period,
        string $search = '',
        string $searchType = 'combined',
        ?string $selectedCategory = null,
        bool $showOnlyWithSales = true,
        array $filters = [],
        bool $exactMatch = false,
        bool $fuzzySearch = true
    ): void {
        unset($this->stockAlerts);
    }

    #[On('product-sync-started')]
    public function handleSyncStarted(): void
    {
        unset($this->stockAlerts);
    }

    #[Computed]
    public function stockAlerts(): Collection
    {
        $cached = Cache::get('product_metrics_7d');

        if ($cached && isset($cached['stock_alerts'])) {
            return collect($cached['stock_alerts']);
        }

        return collect();
    }

    public function render(): View
    {
        return view('livewire.products.stock-alerts');
    }

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
