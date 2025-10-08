<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ComparisonResult;
use App\ValueObjects\Analytics\AnalyticsFilter;
use App\ValueObjects\Analytics\DateRange;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class EnhancedSalesAnalytics extends Component
{
    // Reactive properties with URL binding
    #[Url(except: '')]
    public string $startDate = '';

    #[Url(except: '')]
    public string $endDate = '';

    #[Url(except: 'last_30_days')]
    public string $preset = 'last_30_days';

    #[Url(except: [])]
    public array $channels = [];

    #[Url(except: [])]
    public array $products = [];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'revenue')]
    public string $sortBy = 'received_date';

    #[Url(except: 'desc')]
    public string $sortDirection = 'desc';

    // View state
    public string $activeTab = 'overview';
    public bool $showComparison = false;

    public function mount(): void
    {
        // Initialize dates if not set
        if (empty($this->startDate) || empty($this->endDate)) {
            $dateRange = DateRange::fromPreset($this->preset);
            $this->startDate = $dateRange->start->toDateString();
            $this->endDate = $dateRange->end->toDateString();
        }
    }

    /**
     * Build the current analytics filter from component properties
     */
    #[Computed]
    public function filter(): AnalyticsFilter
    {
        return AnalyticsFilter::fromArray([
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'channels' => $this->channels,
            'products' => $this->products,
            'search' => $this->search,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ]);
    }

    /**
     * Get analytics service instance
     */
    #[Computed]
    public function analytics(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    /**
     * Get summary statistics
     */
    #[Computed]
    public function summary(): array
    {
        return $this->analytics->getSummary($this->filter);
    }

    /**
     * Get period comparison data
     */
    #[Computed]
    public function comparison(): ComparisonResult
    {
        return $this->analytics->getComparison($this->filter);
    }

    /**
     * Get channel breakdown for drill-down
     */
    #[Computed]
    public function channelBreakdown(): Collection
    {
        return $this->analytics->getChannelBreakdown($this->filter);
    }

    /**
     * Get product breakdown for drill-down
     */
    #[Computed]
    public function productBreakdown(): Collection
    {
        return $this->analytics->getProductBreakdown($this->filter, limit: 20);
    }

    /**
     * Get daily trend data
     */
    #[Computed]
    public function dailyTrend(): Collection
    {
        return $this->analytics->getDailyTrend($this->filter);
    }

    /**
     * Get available channels for filter dropdown
     */
    #[Computed(persist: true, seconds: 3600)]
    public function availableChannels(): Collection
    {
        return $this->analytics->getAvailableChannels();
    }

    /**
     * Apply a date preset
     */
    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $dateRange = DateRange::fromPreset($preset);

        $this->startDate = $dateRange->start->toDateString();
        $this->endDate = $dateRange->end->toDateString();

        // Clear computed property cache
        unset($this->filter);
    }

    /**
     * Toggle channel filter
     */
    public function toggleChannel(string $channel): void
    {
        if (in_array($channel, $this->channels)) {
            $this->channels = array_values(array_diff($this->channels, [$channel]));
        } else {
            $this->channels[] = $channel;
        }

        unset($this->filter);
    }

    /**
     * Clear all filters
     */
    public function clearFilters(): void
    {
        $this->channels = [];
        $this->products = [];
        $this->search = '';
        $this->preset = 'last_30_days';

        $this->applyPreset($this->preset);
    }

    /**
     * Drill down into a specific channel
     */
    public function drillDownChannel(string $channel): void
    {
        $this->channels = [$channel];
        $this->activeTab = 'channels';

        unset($this->filter);
    }

    /**
     * Drill down into a specific product
     */
    public function drillDownProduct(string $sku)
    {
        return redirect()->route('products.detail', ['sku' => $sku]);
    }

    /**
     * Toggle comparison view
     */
    public function toggleComparison(): void
    {
        $this->showComparison = !$this->showComparison;
    }

    /**
     * Update sort
     */
    public function updateSort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }

        unset($this->filter);
    }

    /**
     * Export data (placeholder for future implementation)
     */
    public function export(string $format = 'csv'): void
    {
        // TODO: Implement export functionality
        $this->dispatch('show-notification', [
            'message' => 'Export functionality coming soon!',
            'type' => 'info',
        ]);
    }

    public function render()
    {
        return view('livewire.analytics.enhanced-sales-analytics')
            ->layout('components.layouts.app');
    }
}
