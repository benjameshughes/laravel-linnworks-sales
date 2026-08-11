<?php

declare(strict_types=1);

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

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

    public function getProductSalesData(string $sku, ?int $period = null): array
    {
        $query = OrderItem::where('sku', $sku)
            ->whereHas('order', function ($q) use ($period) {
                if ($period !== null) {
                    $q->whereBetween('received_at', [Carbon::now()->subDays($period), Carbon::now()]);
                }
            });

        $items = $query->get();

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

    public function getProductChannelPerformance(string $sku, ?int $period = null): SupportCollection
    {
        $items = OrderItem::where('sku', $sku)
            ->with('order:id,source')
            ->whereHas('order', function ($q) use ($period) {
                if ($period !== null) {
                    $q->whereBetween('received_at', [Carbon::now()->subDays($period), Carbon::now()]);
                }
            })
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

    public function getCategorySalesData(?int $period = null): SupportCollection
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku');

        if ($period !== null) {
            $query->whereBetween('orders.received_at', [Carbon::now()->subDays($period), Carbon::now()]);
        }

        $categoryData = $query->select(
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

    public function getBulkProductSalesData(array $skus, ?int $period = null): SupportCollection
    {
        if (empty($skus)) {
            return collect();
        }

        $query = OrderItem::whereIn('sku', $skus)
            ->join('orders', 'order_items.order_id', '=', 'orders.id');

        if ($period !== null) {
            $query->whereBetween('orders.received_at', [Carbon::now()->subDays($period), Carbon::now()]);
        }

        $salesData = $query->select(
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

    /**
     * Count distinct products with sales in a period (DB aggregation, no limit).
     */
    public function countProductsWithSales(int $period, ?string $search = null, ?string $category = null): int
    {
        $fromDate = Carbon::now()->subDays($period);
        $toDate = Carbon::now();

        $query = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereBetween('received_at', [$fromDate, $toDate]));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('item_title', 'LIKE', '%'.$search.'%')
                    ->orWhere('sku', 'LIKE', '%'.$search.'%');
            });
        }

        if ($category) {
            $query->where('category_name', $category);
        }

        return $query->distinct('sku')->count('sku');
    }

    public function getOutOfStockProducts(int $limit = 20): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock_available', '<=', 0)
            ->select(['id', 'sku', 'title', 'stock_available', 'stock_minimum', 'category_name'])
            ->limit($limit)
            ->get();
    }

    public function getCategories(): SupportCollection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_name')
            ->distinct()
            ->pluck('category_name')
            ->filter();
    }

    public function getProductSalesAggregation(Carbon $start, Carbon $end, int $limit = 10): SupportCollection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku')
            ->whereBetween('orders.received_at', [$start, $end])
            ->select(
                'order_items.sku',
                DB::raw('COALESCE(products.title, order_items.item_title, "Unknown Product") as title'),
                DB::raw('products.purchase_price'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue'),
                DB::raw('SUM(order_items.unit_cost * order_items.quantity) as total_cost'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count')
            )
            ->whereNotNull('order_items.sku')
            ->groupBy('order_items.sku', 'products.title', 'order_items.item_title', 'products.purchase_price')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    public function getCategoryPerformance(Carbon $start, Carbon $end): SupportCollection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku')
            ->whereBetween('orders.received_at', [$start, $end])
            ->select(
                DB::raw('COALESCE(order_items.category_name, products.category_name, "Uncategorized") as category'),
                DB::raw('COUNT(DISTINCT order_items.sku) as product_count'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue')
            )
            ->groupByRaw('COALESCE(order_items.category_name, products.category_name, "Uncategorized")')
            ->orderByDesc('total_revenue')
            ->get();
    }

    public function getProductPerformance(string $sku, Carbon $start, Carbon $end): ?object
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku')
            ->where('order_items.sku', $sku)
            ->whereBetween('orders.received_at', [$start, $end])
            ->select(
                'order_items.sku',
                DB::raw('COALESCE(products.title, order_items.item_title, "Unknown Product") as title'),
                DB::raw('products.purchase_price'),
                DB::raw('products.retail_price'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue'),
                DB::raw('SUM(order_items.unit_cost * order_items.quantity) as total_cost'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count'),
                DB::raw('AVG(order_items.price_per_unit) as avg_selling_price')
            )
            ->groupBy('order_items.sku', 'products.title', 'order_items.item_title', 'products.purchase_price', 'products.retail_price')
            ->first();
    }

    public function getProductDailySalesRange(string $sku, Carbon $start, Carbon $end): SupportCollection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.sku', $sku)
            ->whereBetween('orders.received_at', [$start, $end])
            ->select(
                DB::raw('DATE(orders.received_at) as date'),
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as revenue')
            )
            ->groupByRaw('DATE(orders.received_at)')
            ->orderBy('date')
            ->get();
    }

    public function getProductChannelBreakdown(string $sku, Carbon $start, Carbon $end): SupportCollection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.sku', $sku)
            ->whereBetween('orders.received_at', [$start, $end])
            ->select(
                'orders.source as channel',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as order_count')
            )
            ->groupBy('orders.source')
            ->orderByDesc('revenue')
            ->get();
    }

    public function getProductsWithMargins(Carbon $start, Carbon $end, int $limit = 50): SupportCollection
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku')
            ->whereBetween('orders.received_at', [$start, $end])
            ->whereNotNull('products.purchase_price')
            ->where('products.purchase_price', '>', 0)
            ->select(
                'order_items.sku',
                DB::raw('COALESCE(products.title, order_items.item_title, "Unknown Product") as title'),
                DB::raw('products.purchase_price'),
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue'),
                DB::raw('SUM(products.purchase_price * order_items.quantity) as total_cost'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) - SUM(products.purchase_price * order_items.quantity) as total_profit')
            )
            ->groupBy('order_items.sku', 'products.title', 'order_items.item_title', 'products.purchase_price')
            ->orderByDesc('total_profit')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $margin = $row->total_revenue > 0
                    ? (($row->total_revenue - $row->total_cost) / $row->total_revenue) * 100
                    : 0;

                return (object) array_merge((array) $row, [
                    'margin_percentage' => round($margin, 2),
                ]);
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
