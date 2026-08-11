<?php

declare(strict_types=1);

namespace App\Queries;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class ProductQueries
{
    public function activeProducts(?string $search = null, ?string $category = null, int $limit = 200): Collection
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

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    public function lowStock(int $limit = 10): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_available', '<=', 'stock_minimum')
            ->select(['id', 'sku', 'title', 'stock_available', 'stock_minimum'])
            ->orderBy('stock_available')
            ->limit($limit)
            ->get();
    }

    public function outOfStock(int $limit = 20): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock_available', '<=', 0)
            ->select(['id', 'sku', 'title', 'stock_available', 'stock_minimum', 'category_name'])
            ->limit($limit)
            ->get();
    }

    public function categories(): SupportCollection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_name')
            ->distinct()
            ->pluck('category_name')
            ->filter();
    }

    public function countWithSales(int $period, ?string $search = null, ?string $category = null): int
    {
        $query = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereBetween('received_at', [now()->subDays($period), now()]));

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

    public function bulkSalesData(array $skus, ?int $period = null): SupportCollection
    {
        if (empty($skus)) {
            return collect();
        }

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('order_items.sku', $skus);

        if ($period !== null) {
            $query->whereBetween('orders.received_at', [now()->subDays($period), now()]);
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

    public function categorySalesData(?int $period = null): SupportCollection
    {
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku');

        if ($period !== null) {
            $query->whereBetween('orders.received_at', [now()->subDays($period), now()]);
        }

        return $query->select(
            DB::raw('COALESCE(order_items.category_name, products.category_name, "Uncategorized") as category'),
            DB::raw('COUNT(DISTINCT order_items.sku) as product_count'),
            DB::raw('SUM(order_items.quantity) as total_quantity'),
            DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue')
        )
            ->groupByRaw('COALESCE(order_items.category_name, products.category_name, "Uncategorized")')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'product_count' => (int) $item->product_count,
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => (float) $item->total_revenue,
            ]);
    }

    public function productPerformance(string $sku, Carbon $start, Carbon $end): ?object
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

    public function dailySales(string $sku, Carbon $start, Carbon $end): SupportCollection
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

    public function channelBreakdown(string $sku, Carbon $start, Carbon $end): SupportCollection
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

    public function productsWithMargins(Carbon $start, Carbon $end, int $limit = 50): SupportCollection
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

    public function parentPerformance(?int $period = null, int $limit = 20): SupportCollection
    {
        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.sku', '=', 'p.sku')
            ->join('product_parents as pp', 'p.product_parent_id', '=', 'pp.id');

        if ($period !== null) {
            $query->whereBetween('o.received_at', [now()->subDays($period), now()]);
        }

        return $query->select(
            'pp.id',
            'pp.sku as parent_sku',
            'pp.title as parent_title',
            DB::raw('COUNT(DISTINCT p.sku) as variant_count'),
            DB::raw('SUM(oi.quantity) as total_quantity'),
            DB::raw('COUNT(DISTINCT oi.order_id) as order_count'),
            DB::raw('SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) as total_revenue'),
            DB::raw('SUM(COALESCE(NULLIF(oi.unit_cost, 0), p.purchase_price, 0) * oi.quantity) as total_cost'),
        )
            ->groupBy('pp.id', 'pp.sku', 'pp.title')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $profit = (float) $row->total_revenue - (float) $row->total_cost;
                $margin = $row->total_revenue > 0
                    ? ($profit / $row->total_revenue) * 100
                    : 0;

                return (object) array_merge((array) $row, [
                    'total_profit' => $profit,
                    'margin_percentage' => round($margin, 2),
                ]);
            });
    }
}
