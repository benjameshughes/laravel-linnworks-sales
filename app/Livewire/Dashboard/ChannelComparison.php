<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Livewire\Component;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use App\Enums\ComparisonMode;
use Livewire\Attributes\Computed;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use App\ValueObjects\GrowthIndicator;
use App\DataTransferObjects\ChannelPerformance;
use App\Services\Metrics\ChannelComparisonQuery;

/**
 * Period-over-period channel comparison.
 *
 * Anchors on a single month and compares it against either the previous month
 * (MoM) or the same month a year earlier (YoY). All filters are URL-bound so a
 * given comparison can be shared as a link.
 */
final class ChannelComparison extends Component
{
    #[Url(as: 'mode')]
    public string $mode = 'mom';

    #[Url(as: 'month')]
    public string $month = '';

    #[Url(as: 'status')]
    public string $status = 'all';

    #[Url(as: 'source')]
    public string $source = 'all';

    #[Url(as: 'subsources')]
    public bool $includeSubsources = false;

    #[Url(as: 'sort')]
    public string $sortBy = 'revenue';

    private const MONTH_FORMAT = 'Y-m';

    private const MONEY_DECIMALS = 2;

    private const CHART_CHANNEL_LIMIT = 10;

    /**
     * Normalising both properties here keeps the controls in step with the
     * data - an unparseable month in the query string would otherwise leave
     * the picker showing one month while the page reported another.
     */
    public function mount(): void
    {
        if (ComparisonMode::tryFrom($this->mode) === null) {
            $this->mode = ComparisonMode::MonthOverMonth->value;
        }

        $this->month = $this->resolveAnchor()->format(self::MONTH_FORMAT);
    }

    public function toggleSubsources(): void
    {
        $this->includeSubsources = ! $this->includeSubsources;
    }

    #[Computed]
    public function comparisonMode(): ComparisonMode
    {
        return ComparisonMode::tryFrom($this->mode) ?? ComparisonMode::MonthOverMonth;
    }

    #[Computed]
    public function currentLabel(): string
    {
        return $this->comparison['current_label'];
    }

    #[Computed]
    public function baselineLabel(): string
    {
        return $this->comparison['baseline_label'];
    }

    /**
     * @return array{
     *     channels: Collection<int, ChannelPerformance>,
     *     trend: array{labels: array<int, string>, current: array<int, float>, baseline: array<int, float>},
     *     current_label: string,
     *     baseline_label: string
     * }
     */
    #[Computed]
    public function comparison(): array
    {
        return (new ChannelComparisonQuery(
            mode: $this->comparisonMode,
            anchor: $this->resolveAnchor(),
            includeSubsources: $this->includeSubsources,
            status: $this->status,
            source: $this->source,
        ))->compare();
    }

    /**
     * A month the user cannot reach through the UI can still arrive via the
     * query string, so fall back rather than trusting it.
     */
    private function resolveAnchor(): CarbonImmutable
    {
        return ChannelComparisonQuery::parseMonth($this->month)
            ?? ChannelComparisonQuery::defaultAnchor($this->comparisonMode);
    }

    /**
     * @return Collection<int, ChannelPerformance>
     */
    #[Computed]
    public function channels(): Collection
    {
        return $this->comparison['channels']->sortByDesc(
            fn (ChannelPerformance $channel): float => match ($this->sortBy) {
                'orders' => (float) $channel->currentOrders,
                'items' => (float) $channel->currentItems,
                'growth' => $channel->revenueGrowth() ?? -INF,
                'delta' => $channel->revenueDelta(),
                default => $channel->currentRevenue,
            }
        )->values();
    }

    #[Computed]
    public function totals(): array
    {
        $channels = $this->channels;

        $currentRevenue = $channels->sum(fn (ChannelPerformance $c): float => $c->currentRevenue);
        $baselineRevenue = $channels->sum(fn (ChannelPerformance $c): float => $c->baselineRevenue);
        $currentOrders = $channels->sum(fn (ChannelPerformance $c): int => $c->currentOrders);
        $baselineOrders = $channels->sum(fn (ChannelPerformance $c): int => $c->baselineOrders);

        $currentAov = $currentOrders > 0 ? $currentRevenue / $currentOrders : 0.0;
        $baselineAov = $baselineOrders > 0 ? $baselineRevenue / $baselineOrders : 0.0;

        return [
            'current_revenue' => $currentRevenue,
            'baseline_revenue' => $baselineRevenue,
            'revenue_indicator' => $this->indicator($currentRevenue, $baselineRevenue),
            'current_orders' => $currentOrders,
            'baseline_orders' => $baselineOrders,
            'orders_indicator' => $this->indicator((float) $currentOrders, (float) $baselineOrders),
            'current_aov' => $currentAov,
            'baseline_aov' => $baselineAov,
            'aov_indicator' => $this->indicator($currentAov, $baselineAov),
            'winners' => $channels->filter(fn (ChannelPerformance $c): bool => $c->revenueDelta() > 0)->count(),
            'losers' => $channels->filter(fn (ChannelPerformance $c): bool => $c->revenueDelta() < 0)->count(),
        ];
    }

    /**
     * Percentage movement between two totals, presented ready for the badge.
     * A zero baseline is unmeasurable rather than infinite.
     */
    private function indicator(float $current, float $baseline): GrowthIndicator
    {
        $percentage = $baseline > 0 ? (($current - $baseline) / $baseline) * 100 : null;

        return GrowthIndicator::fromPercentage($percentage, 'No baseline');
    }

    /**
     * Grouped bar chart - current vs baseline revenue for the top channels.
     */
    #[Computed]
    public function revenueChart(): array
    {
        $channels = $this->channels->take(self::CHART_CHANNEL_LIMIT);

        return [
            'labels' => $channels->map(fn (ChannelPerformance $c): string => $c->name)->all(),
            'datasets' => [
                [
                    'label' => $this->comparison['current_label'],
                    'data' => $channels->map(fn (ChannelPerformance $c): float => round($c->currentRevenue, self::MONEY_DECIMALS))->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.85)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => $this->comparison['baseline_label'],
                    'data' => $channels->map(fn (ChannelPerformance $c): float => round($c->baselineRevenue, self::MONEY_DECIMALS))->all(),
                    'backgroundColor' => 'rgba(148, 163, 184, 0.6)',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    /**
     * Daily revenue overlay - both periods sharing a day-of-month axis.
     */
    #[Computed]
    public function trendChart(): array
    {
        $trend = $this->comparison['trend'];

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                [
                    'label' => $this->comparison['current_label'],
                    'data' => $this->roundSeries($trend['current']),
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.35,
                    'fill' => true,
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                ],
                [
                    'label' => $this->comparison['baseline_label'],
                    'data' => $this->roundSeries($trend['baseline']),
                    'borderColor' => '#94A3B8',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.35,
                    'fill' => false,
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    /**
     * Nulls mark days the month never had, and must survive rounding so
     * Chart.js keeps breaking the line there.
     *
     * @param  array<int, float|null>  $series
     * @return array<int, float|null>
     */
    private function roundSeries(array $series): array
    {
        return collect($series)
            ->map(fn (?float $value): ?float => $value === null ? null : round($value, self::MONEY_DECIMALS))
            ->all();
    }

    /**
     * @return Collection<string, string>
     */
    #[Computed]
    public function availableMonths(): Collection
    {
        return ChannelComparisonQuery::availableMonths();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function availableSources(): Collection
    {
        return ChannelComparisonQuery::availableSources();
    }

    #[Computed]
    public function channelCount(): int
    {
        return $this->channels->count();
    }

    #[Computed]
    public function progressLabel(): string
    {
        return sprintf(
            '%d of %d days',
            $this->comparison['elapsed_days'],
            $this->comparison['total_days'],
        );
    }

    /**
     * True when the baseline period holds no orders at all, which makes every
     * channel read as "New" and the comparison meaningless.
     */
    #[Computed]
    public function hasBaselineData(): bool
    {
        return $this->totals['baseline_orders'] > 0;
    }

    /**
     * True while the anchor month is still running, so partial figures are not
     * mistaken for a full month's trading.
     */
    #[Computed]
    public function isInProgress(): bool
    {
        return $this->comparison['elapsed_days'] < $this->comparison['total_days'];
    }

    public function render(): View
    {
        return view('livewire.dashboard.channel-comparison')
            ->title('Channel Comparison');
    }
}
