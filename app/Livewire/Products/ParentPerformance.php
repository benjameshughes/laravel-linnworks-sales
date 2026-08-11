<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Queries\ProductQueries;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;

final class ParentPerformance extends Component
{
    public string $period = '30';

    public function mount(): void
    {
        $this->period = request('period', '30');
    }

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
        $this->period = $period;

        unset($this->parents);
    }

    #[Computed]
    public function parents(): Collection
    {
        return app(ProductQueries::class)->parentPerformance(
            period: (int) $this->period,
            limit: 20
        );
    }

    public function render(): View
    {
        return view('livewire.products.parent-performance');
    }

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
