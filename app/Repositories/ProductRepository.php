<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

final class ProductRepository
{
    public function getActiveProducts(?string $search = null, ?string $category = null, int $limit = 200): Collection
    {
        $query = Product::query()
            ->where('is_active', true)
            ->select(['id', 'sku', 'title', 'category_name', 'purchase_price', 'stock_available', 'stock_minimum']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', '%'.$search.'%')
                    ->orWhere('sku', 'LIKE', '%'.$search.'%');
            });
        }

        if ($category) {
            $query->where('category_name', $category);
        }

        return $query->limit($limit)->get();
    }

    public function getLowStockProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_available', '<=', 'stock_minimum')
            ->select(['id', 'sku', 'title', 'stock_available', 'stock_minimum'])
            ->limit($limit)
            ->get();
    }

    public function getProductsByCategoryOptimized(): SupportCollection
    {
        return Product::query()
            ->where('is_active', true)
            ->select(['id', 'sku', 'title', 'category_name', 'purchase_price'])
            ->get()
            ->groupBy(fn ($product) => $product->category_name ?: 'Uncategorized');
    }

    public function getProductSalesData(string $sku): array
    {
        // Use the normalized order_items table for better performance
        $items = OrderItem::where('sku', $sku)
            ->whereHas('order')
            ->get();

        if ($items->isEmpty()) {
            return [
                'total_sold' => 0,
                'total_revenue' => 0,
                'avg_selling_price' => 0,
                'order_count' => 0,
            ];
        }

        $orderCount = $items->pluck('order_id')->unique()->count();
        $totalSold = $items->sum('quantity');
        $totalRevenue = $items->sum(fn ($item) => $this->calculateItemRevenue($item));
        $avgPrice = $items->filter(fn ($item) => (float) $item->price_per_unit > 0)->avg('price_per_unit');
        if (! $avgPrice && $totalSold > 0) {
            $avgPrice = $totalRevenue / $totalSold;
        }

        return [
            'total_sold' => $totalSold,
            'total_revenue' => $totalRevenue,
            'avg_selling_price' => $avgPrice ?? 0,
            'order_count' => $orderCount,
        ];
    }

    public function getProductChannelPerformance(string $sku): SupportCollection
    {
        // Use the normalized order_items table with eager loading
        $items = OrderItem::where('sku', $sku)
            ->with('order:id,source')
            ->whereHas('order')
            ->get();

        return $items->groupBy('order.source')
            ->map(function ($channelItems, $channel) {
                $orderIds = $channelItems->pluck('order_id')->unique();
                $quantity = $channelItems->sum('quantity');
                $revenue = $channelItems->sum(fn ($item) => $this->calculateItemRevenue($item));
                $avgPrice = $channelItems->filter(fn ($item) => (float) $item->price_per_unit > 0)->avg('price_per_unit');
                if (! $avgPrice && $quantity > 0) {
                    $avgPrice = $revenue / $quantity;
                }

                return [
                    'channel' => $channel ?? 'Unknown',
                    'quantity_sold' => $quantity,
                    'revenue' => $revenue,
                    'order_count' => $orderIds->count(),
                    'avg_price' => $avgPrice ?? 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    public function getProductDailySales(string $sku, Carbon $startDate): SupportCollection
    {
        // Use the normalized order_items table with proper joins
        $dailySales = OrderItem::where('sku', $sku)
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.received_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(orders.received_at) as sale_date'),
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as revenue')
            )
            ->groupBy('sale_date')
            ->get();

        return $dailySales->keyBy('sale_date')
            ->map(function ($item) {
                return (object) [
                    'sale_date' => $item->sale_date,
                    'quantity' => (int) $item->quantity,
                    'revenue' => (float) $item->revenue,
                ];
            });
    }

    public function getCategorySalesData(): SupportCollection
    {
        // Use the normalized order_items table with proper aggregation
        $categoryData = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku')
            ->select(
                DB::raw('COALESCE(order_items.category_name, products.category_name, "Uncategorized") as category'),
                DB::raw('COUNT(DISTINCT order_items.sku) as product_count'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue')
            )
            ->groupByRaw('COALESCE(order_items.category_name, products.category_name, "Uncategorized")')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return $categoryData->map(function ($item) {
            return [
                'category' => $item->category,
                'product_count' => (int) $item->product_count,
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
            ];
        });
    }

    public function getProductsByIds(array $ids): Collection
    {
        return Product::whereIn('id', $ids)
            ->select(['id', 'sku', 'title', 'category_name', 'purchase_price', 'stock_available', 'stock_minimum'])
            ->get();
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)
            ->select(['id', 'sku', 'title', 'category_name', 'purchase_price', 'stock_available', 'stock_minimum', 'stock_in_orders', 'stock_due'])
            ->first();
    }

    public function getOrdersContainingProduct(string $sku, ?Carbon $startDate = null): Collection
    {
        $query = Order::whereHas('orderItems', function ($q) use ($sku) {
            $q->where('sku', $sku);
        })
            ->with(['orderItems' => function ($q) use ($sku) {
                $q->where('sku', $sku);
            }])
            ->select(['id', 'number', 'source', 'received_at', 'total_charge']);

        if ($startDate) {
            $query->where('received_at', '>=', $startDate);
        }

        return $query->get();
    }

    public function getBulkProductSalesData(array $skus): SupportCollection
    {
        if (empty($skus)) {
            return collect();
        }

        // Use the normalized order_items table for bulk operations
        $salesData = OrderItem::whereIn('sku', $skus)
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->select(
                'order_items.sku',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue'),
                DB::raw('AVG(NULLIF(order_items.price_per_unit, 0)) as avg_selling_price'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count')
            )
            ->groupBy('order_items.sku')
            ->get()
            ->keyBy('sku');

        // Build result for all requested SKUs (including ones with no sales)
        return collect($skus)->mapWithKeys(function ($sku) use ($salesData) {
            $data = $salesData->get($sku);

            return [$sku => [
                'total_sold' => $data ? (int) $data->total_sold : 0,
                'total_revenue' => $data ? (float) $data->total_revenue : 0,
                'avg_selling_price' => $data && $data->avg_selling_price !== null
                    ? (float) $data->avg_selling_price
                    : ($data && (int) $data->total_sold > 0
                        ? (float) $data->total_revenue / (int) $data->total_sold
                        : 0),
                'order_count' => $data ? (int) $data->order_count : 0,
            ]];
        });
    }

    private function calculateItemRevenue(OrderItem $item): float
    {
        $lineTotal = (float) $item->line_total;
        if ($lineTotal > 0) {
            return $lineTotal;
        }

        $pricePerUnit = (float) $item->price_per_unit;
        $quantity = (int) $item->quantity;

        if ($pricePerUnit > 0 && $quantity > 0) {
            return $pricePerUnit * $quantity;
        }

        $attributes = $item->additional_info ?? [];
        $metaPrice = isset($attributes['price_per_unit']) ? (float) $attributes['price_per_unit'] : 0.0;
        if ($metaPrice > 0 && $quantity > 0) {
            return $metaPrice * $quantity;
        }

        return 0.0;
    }
}
