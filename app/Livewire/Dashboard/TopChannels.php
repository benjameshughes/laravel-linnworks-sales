<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardDataService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class TopChannels extends Component
{
    public string $period = '7';

    public string $channel = 'all';

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
        string $status = 'all',
        ?string $customFrom = null,
        ?string $customTo = null
    ): void {
        $this->period = $period;
        $this->channel = $channel;
        $this->status = $status;
        $this->customFrom = $customFrom;
        $this->customTo = $customTo;
    }

    #[Computed]
    public function topChannels(): Collection
    {
        // CACHE-ONLY MODE: No fallback to prevent OOM on large periods
        $service = app(DashboardDataService::class);
        if ($service->canUseCachedMetrics($this->period, $this->channel, $this->status, $this->customFrom, $this->customTo)) {
            $cached = $service->getCachedMetrics($this->period, $this->channel, $this->status);
            if ($cached && isset($cached['top_channels'])) {
                // Cache returns arrays - wrap each channel in collect() for blade compatibility
                return collect($cached['top_channels'])->map(fn ($item) => collect($item));
            }
        }

        // Return empty collection if cache unavailable (prevents OOM on large datasets)
        return collect();
    }

    public function render()
    {
        return view('livewire.dashboard.top-channels');
    }

    /**
     * Skeleton loader shown while lazy loading
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.top-list', $params);
    }
}
