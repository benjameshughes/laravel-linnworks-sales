<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read \Illuminate\Support\Collection $channelComparison
 * @property-read array|null $channelDetails
 * @property-read array $chartData
 */
class ChannelComparison extends Component
{
    public string $period = '30';

    public string $metric = 'revenue';

    public ?string $selectedChannel = null;

    public bool $showSubsources = false;

    public function mount()
    {
        //
    }

    #[Computed]
    public function channelComparison()
    {
        // Get cached channel data
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        if (! $periodEnum || ! $periodEnum->isCacheable()) {
            return collect();
        }

        $cacheKey = $periodEnum->cacheKey('all', 'all');
        $cached = Cache::get($cacheKey);

        if (! $cached || ! isset($cached['top_channels'])) {
            return collect();
        }

        // Transform cached top_channels data to match expected format
        return collect($cached['top_channels'])->map(function ($channel) {
            return [
                'channel' => $channel['name'],
                'total_revenue' => $channel['revenue'],
                'total_orders' => $channel['orders'],
                'total_items' => 0, // Not available in cache yet
                'avg_order_value' => $channel['avg_order_value'],
                'total_profit' => 0, // Not available in cache yet
                'profit_margin' => 0, // Not available in cache yet
                'conversion_rate' => 100, // Not tracked yet
                'revenue_share' => $channel['percentage'],
                'growth_rate' => 0, // Not available in cache yet
            ];
        })->sortByDesc($this->metric === 'revenue' ? 'total_revenue' : $this->metric);
    }

    #[Computed]
    public function channelDetails()
    {
        if (! $this->selectedChannel) {
            return null;
        }

        $channelData = $this->channelComparison->firstWhere('channel', $this->selectedChannel);

        if (! $channelData) {
            return null;
        }

        // TODO: Add detailed channel breakdown to cache warming
        // For now, return basic data without daily breakdown or top products
        $channelData['daily_data'] = [];
        $channelData['top_products'] = [];

        return $channelData;
    }

    #[Computed]
    public function chartData()
    {
        $comparison = $this->channelComparison;

        return [
            'labels' => $comparison->pluck('channel')->take(10)->toArray(),
            'revenue' => $comparison->pluck('total_revenue')->take(10)->toArray(),
            'orders' => $comparison->pluck('total_orders')->take(10)->toArray(),
            'profit' => $comparison->pluck('total_profit')->take(10)->toArray(),
            'margins' => $comparison->pluck('profit_margin')->take(10)->toArray(),
        ];
    }

    public function selectChannel(string $channel)
    {
        $this->selectedChannel = $channel;
    }

    public function clearSelection()
    {
        $this->selectedChannel = null;
    }

    public function toggleSubsources()
    {
        $this->showSubsources = ! $this->showSubsources;
    }

    public function updatedMetric()
    {
        // Re-sort when metric changes
    }

    public function updatedPeriod()
    {
        $this->clearSelection();
    }

    public function render()
    {
        return view('livewire.dashboard.channel-comparison')
            ->title('Channel Comparison');
    }
}
