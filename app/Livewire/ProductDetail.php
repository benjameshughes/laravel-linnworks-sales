<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Metrics\ProductMetrics;
use App\Services\ProductBadgeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Product Detail')]
class ProductDetail extends Component
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

    #[Computed]
    public function metrics(): ProductMetrics
    {
        $fromDate = Carbon::now()->subDays($this->period);
        $toDate = Carbon::now();

        $orders = Order::whereBetween('received_date', [$fromDate, $toDate])
            ->whereHas('orderItems', function ($query) {
                $query->where('sku', $this->sku);
            })
            ->with(['orderItems' => function ($query) {
                $query->where('sku', $this->sku);
            }])
            ->get();

        return new ProductMetrics($orders);
    }

    #[Computed]
    public function salesTrend(): Collection
    {
        $fromDate = Carbon::now()->subDays($this->period);
        $trend = collect();

        for ($i = $this->period; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $dayData = OrderItem::where('sku', $this->sku)
                ->whereHas('order', function ($query) use ($dayStart, $dayEnd) {
                    $query->whereBetween('received_date', [$dayStart, $dayEnd]);
                })
                ->selectRaw('
                    SUM(quantity) as total_quantity,
                    SUM(line_total) as total_revenue,
                    COUNT(DISTINCT order_id) as order_count
                ')
                ->first();

            $trend->push([
                'date' => $date->format('M j'),
                'full_date' => $date->format('Y-m-d'),
                'quantity' => $dayData->total_quantity ?? 0,
                'revenue' => $dayData->total_revenue ?? 0,
                'orders' => $dayData->order_count ?? 0,
            ]);
        }

        return $trend;
    }

    #[Computed]
    public function channelPerformance(): Collection
    {
        $fromDate = Carbon::now()->subDays($this->period);
        $toDate = Carbon::now();

        return OrderItem::where('sku', $this->sku)
            ->whereHas('order', function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('received_date', [$fromDate, $toDate]);
            })
            ->with('order')
            ->get()
            ->groupBy('order.channel_name')
            ->map(function ($items, $channel) {
                return [
                    'channel' => $channel ?? 'Unknown',
                    'quantity_sold' => $items->sum('quantity'),
                    'revenue' => $items->sum('line_total'),
                    'order_count' => $items->unique('order_id')->count(),
                    'avg_order_value' => $items->count() > 0 ? $items->sum('line_total') / $items->unique('order_id')->count() : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    #[Computed]
    public function recentOrders(): Collection
    {
        $fromDate = Carbon::now()->subDays($this->period);
        $toDate = Carbon::now();

        return Order::whereHas('orderItems', function ($query) {
            $query->where('sku', $this->sku);
        })
            ->whereBetween('received_date', [$fromDate, $toDate])
            ->with(['orderItems' => function ($query) {
                $query->where('sku', $this->sku);
            }])
            ->orderByDesc('received_date')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                $item = $order->orderItems->first();

                return [
                    'order_number' => $order->order_number,
                    'date' => $order->received_date->format('M j, Y'),
                    'channel' => $order->channel_name,
                    'quantity' => $item->quantity,
                    'revenue' => $item->line_total,
                    'price_per_unit' => $item->price_per_unit,
                ];
            });
    }

    #[Computed]
    public function profitAnalysis(): array
    {
        $fromDate = Carbon::now()->subDays($this->period);
        $toDate = Carbon::now();

        $items = OrderItem::where('sku', $this->sku)
            ->whereHas('order', function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('received_date', [$fromDate, $toDate]);
            })
            ->get();

        $totalRevenue = $items->sum('line_total');
        $totalCost = $items->sum(function ($item) {
            return $item->unit_cost * $item->quantity;
        });
        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'profit_margin' => $profitMargin,
            'total_sold' => $items->sum('quantity'),
            'avg_selling_price' => $items->count() > 0 ? $totalRevenue / $items->sum('quantity') : 0,
            'avg_unit_cost' => $items->count() > 0 ? $totalCost / $items->sum('quantity') : 0,
        ];
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
        } else {
            return 'in_stock';
        }
    }

    public function render()
    {
        return view('livewire.product-detail');
    }
}
