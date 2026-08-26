<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use App\Services\Metrics\ChunkedMetricsCalculator;

final class TopChannels extends Component
{
    public string $period = '7';

    public string $channel = 'all';

    public string $subsource = 'all';

    public string $status = 'all';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public function mount(): void
    {
        $this->period = request('period', '7');
        $this->channel = request('channel', 'all');
        $this->status = request('status', 'all');
    }

    #[On('filters-updated')]
    public function updateFilters(
        string $period,
        string $channel,
        string $subsource = 'all',
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->subsource = $subsource;
        $this->status = $status;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
    }

    #[Computed]
    public function topChannels(): Collection
    {
        $periodEnum = \App\Enums\Period::tryFrom($this->period);

        if ($this->customFrom || $this->customTo || ! $periodEnum?->isCacheable()) {
            $calculator = new ChunkedMetricsCalculator(
                period: $this->period,
                channel: $this->channel,
                status: $this->status,
                customFrom: $this->customFrom,
                customTo: $this->customTo,
                subsource: $this->subsource,
            );

            $data = $calculator->calculate();

            return $data['top_channels'];
        }

        $cacheKey = $periodEnum->cacheKey($this->channel, $this->subsource, $this->status);
        $cached = Cache::get($cacheKey);

        if ($cached && isset($cached['top_channels'])) {
            return $cached['top_channels'];
        }

        // Cache miss - return empty collection to prevent OOM
        return collect();
    }

    public function render(): View
    {
        return view('livewire.dashboard.top-channels');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = []): View
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
