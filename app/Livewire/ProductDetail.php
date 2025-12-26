<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use App\Services\Metrics\Products\ProductService;
use App\Services\ProductBadgeService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read \Illuminate\Support\Collection|null $performance
 * @property-read \Illuminate\Support\Collection $salesTrend
 * @property-read \Illuminate\Support\Collection $channelPerformance
 * @property-read array $profitAnalysis
 * @property-read \Illuminate\Support\Collection $recentOrders
 * @property-read \Illuminate\Support\Collection $productBadges
 * @property-read array $stockInfo
 */
#[Layout('components.layouts.app')]
#[Title('Product Detail')]
final class ProductDetail extends Component
{
    public string $sku;

    public int $period = 30;

    public ?Product $product = null;

    public function mount(string $sku): void
    {
        $this->sku = $sku;
        $this->product = Product::where('sku', $sku)->first();

        if (! $this->product) {
            abort(404, 'Product not found');
        }
    }

    /**
     * Main performance data from ProductService - single query, all data.
     */
    #[Computed]
    public function performance(): ?Collection
    {
        return app(ProductService::class)->getProductPerformance(
            sku: $this->sku,
            period: (string) $this->period
        );
    }

    /**
     * Sales trend for chart - derived from performance.
     */
    #[Computed]
    public function salesTrend(): Collection
    {
        $performance = $this->performance;

        if (! $performance || ! $performance->get('daily_sales')) {
            return collect();
        }

        return $performance->get('daily_sales')->map(fn ($day) => [
            'date' => \Carbon\Carbon::parse($day->date)->format('M j'),
            'full_date' => $day->date,
            'quantity' => (int) $day->quantity,
            'revenue' => (float) $day->revenue,
            'orders' => 0, // Not available in daily aggregation
        ]);
    }

    /**
     * Channel performance - derived from performance.
     */
    #[Computed]
    public function channelPerformance(): Collection
    {
        $performance = $this->performance;

        if (! $performance || ! $performance->get('channel_breakdown')) {
            return collect();
        }

        return $performance->get('channel_breakdown')->map(fn ($channel) => [
            'channel' => $channel->channel ?? 'Unknown',
            'quantity_sold' => (int) $channel->quantity,
            'revenue' => (float) $channel->revenue,
            'order_count' => (int) $channel->order_count,
            'avg_order_value' => $channel->order_count > 0
                ? (float) $channel->revenue / $channel->order_count
                : 0,
        ])->sortByDesc('revenue')->values();
    }

    /**
     * Profit analysis - derived from performance.
     */
    #[Computed]
    public function profitAnalysis(): array
    {
        $performance = $this->performance;

        if (! $performance) {
            return [
                'total_revenue' => 0,
                'total_cost' => 0,
                'total_profit' => 0,
                'profit_margin' => 0,
                'total_sold' => 0,
                'avg_selling_price' => 0,
                'avg_unit_cost' => 0,
            ];
        }

        $totalRevenue = (float) $performance->get('total_revenue', 0);
        $totalCost = (float) $performance->get('total_cost', 0);
        $totalQuantity = (int) $performance->get('total_quantity', 0);
        $totalProfit = $totalRevenue - $totalCost;

        return [
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'profit_margin' => $performance->get('margin_percentage') ?? 0,
            'total_sold' => $totalQuantity,
            'avg_selling_price' => (float) $performance->get('avg_selling_price', 0),
            'avg_unit_cost' => $totalQuantity > 0 ? $totalCost / $totalQuantity : 0,
        ];
    }

    /**
     * Recent orders - kept as direct query (already efficient with limit).
     */
    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::whereHas('orderItems', fn ($query) => $query->where('sku', $this->sku))
            ->whereBetween('received_at', [now()->subDays($this->period), now()])
            ->with(['orderItems' => fn ($query) => $query->where('sku', $this->sku)])
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'number' => $order->number,
                'date' => $order->received_at->format('M j, Y'),
                'channel' => $order->source,
                'quantity' => $order->orderItems->first()->quantity,
                'revenue' => $order->orderItems->first()->line_total,
                'price_per_unit' => $order->orderItems->first()->price_per_unit,
            ]);
    }

    #[Computed]
    public function productBadges(): Collection
    {
        $badgeService = app(ProductBadgeService::class);
        $badges = $badgeService->getProductBadges($this->product, $this->period);

        return $badges->map(fn ($badge) => $badge->toArray());
    }

    #[Computed]
    public function stockInfo(): array
    {
        return [
            'current_stock' => $this->product->stock_level ?? 0,
            'minimum_stock' => $this->product->stock_minimum ?? 0,
            'stock_status' => $this->getStockStatus(),
            'last_updated' => $this->product->updated_at?->format('M j, Y g:i A') ?? 'Never',
        ];
    }

    public function updatedPeriod(): void
    {
        // Clear cached computed properties when period changes
        unset($this->performance, $this->salesTrend, $this->channelPerformance, $this->profitAnalysis, $this->recentOrders);

        $this->dispatch('period-changed');
    }

    private function getStockStatus(): string
    {
        $currentStock = $this->product->stock_level ?? 0;
        $minimumStock = $this->product->stock_minimum ?? 0;

        if ($currentStock <= 0) {
            return 'out_of_stock';
        } elseif ($currentStock <= $minimumStock) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    public function render()
    {
        return view('livewire.product-detail');
    }
}
