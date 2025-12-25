<?php

declare(strict_types=1);

namespace App\Repositories\Metrics\Products;

use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Data access layer for product analytics.
 *
 * Provides methods to fetch products and related order item data
 * for use in the ProductFactory calculations.
 */
final class ProductRepository
{
    /**
     * Get products that were sold during the given period.
     *
     * @return Collection<int, Product>
     */
    public function getProductsSoldInPeriod(Carbon $start, Carbon $end): Collection
    {
        $skus = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.received_at', [$start, $end])
            ->distinct()
            ->pluck('order_items.sku')
            ->filter();

        return Product::whereIn('sku', $skus)->get();
    }

    /**
     * Get order items for the given period with eager-loaded orders.
     *
     * @return Collection<int, OrderItem>
     */
    public function getOrderItemsForPeriod(Carbon $start, Carbon $end): Collection
    {
        return OrderItem::query()
            ->with('order:id,source,subsource,received_at')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.received_at', [$start, $end])
            ->select('order_items.*')
            ->get();
    }

    /**
     * Get products by SKU list.
     *
     * @param  array<string>  $skus
     * @return Collection<int, Product>
     */
    public function getProductsBySkus(array $skus): Collection
    {
        return Product::whereIn('sku', $skus)
            ->select([
                'id',
                'sku',
                'title',
                'category_name',
                'purchase_price',
                'retail_price',
                'stock_available',
                'stock_minimum',
            ])
            ->get();
    }

    /**
     * Get products with low stock levels.
     *
     * @return Collection<int, Product>
     */
    public function getLowStockProducts(int $limit = 20): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_available', '<=', 'stock_minimum')
            ->select(['id', 'sku', 'title', 'stock_available', 'stock_minimum', 'category_name'])
            ->orderBy('stock_available')
            ->limit($limit)
            ->get();
    }

    /**
     * Get products that are out of stock.
     *
     * @return Collection<int, Product>
     */
    public function getOutOfStockProducts(int $limit = 20): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock_available', '<=', 0)
            ->select(['id', 'sku', 'title', 'stock_available', 'stock_minimum', 'category_name'])
            ->limit($limit)
            ->get();
    }

    /**
     * Get products by category.
     *
     * @return Collection<int, Product>
     */
    public function getProductsByCategory(string $category): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('category_name', $category)
            ->select(['id', 'sku', 'title', 'category_name', 'purchase_price', 'retail_price', 'stock_available'])
            ->get();
    }

    /**
     * Get all active categories.
     *
     * @return SupportCollection<int, string>
     */
    public function getCategories(): SupportCollection
    {
        return Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_name')
            ->distinct()
            ->pluck('category_name')
            ->filter();
    }

    /**
     * Get product sales aggregation for a period (optimized for top products).
     *
     * Returns a collection of sales data grouped by SKU.
     */
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

    /**
     * Get category performance aggregation for a period.
     */
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

    /**
     * Get a single product's performance data.
     */
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

    /**
     * Get daily sales for a product.
     */
    public function getProductDailySales(string $sku, Carbon $start, Carbon $end): SupportCollection
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

    /**
     * Get product channel breakdown.
     */
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

    /**
     * Get products with margin data (for profitability analysis).
     *
     * @return SupportCollection Products with calculated margins
     */
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
}
