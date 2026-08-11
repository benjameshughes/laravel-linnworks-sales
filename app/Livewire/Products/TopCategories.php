<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

final class TopCategories extends Component
{
    public string $period = '30';

    public ?string $selectedCategory = null;

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
        $this->selectedCategory = $selectedCategory;

        unset($this->topCategories);
    }

    public function selectCategory(string $category): void
    {
        $this->dispatch('category-selected', category: $category);
    }

    #[Computed]
    public function topCategories(): Collection
    {
        $cached = Cache::get("product_metrics_{$this->period}d");

        if ($cached && isset($cached['categories'])) {
            return collect($cached['categories']);
        }

        return collect();
    }

    public function render(): View
    {
        return view('livewire.products.top-categories');
    }

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
