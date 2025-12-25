<?php

declare(strict_types=1);

namespace App\Services\Metrics\Products;

use App\Actions\Calculations\Sales\CalculatePeriodDates;
use App\Factories\Metrics\Products\ProductFactory;
use App\Repositories\Metrics\Products\ProductRepository;
use Illuminate\Support\Collection;

/**
 * Orchestration layer for product analytics.
 *
 * Ties together the ProductRepository (data access) and
 * ProductFactory (calculations) to provide product metrics.
 */
final readonly class ProductService
{
    public function __construct(private ProductRepository $productRepo) {}

    /**
     * Get top products by quantity sold.
     */
    public function getTopProducts(
        string $period = '7',
        int $limit = 10,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->productRepo->getProductSalesAggregation(
            $dates['start'],
            $dates['end'],
            $limit
        )->map(fn ($row) => collect([
            'sku' => $row->sku,
            'title' => $row->title !== 'Unknown Product' ? $row->title : 'Unknown Product',
            'quantity' => (int) $row->total_quantity,
            'revenue' => (float) $row->total_revenue,
            'order_count' => (int) $row->order_count,
        ]));
    }

    /**
     * Get top products by revenue.
     */
    public function getTopProductsByRevenue(
        string $period = '7',
        int $limit = 10,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);
        $salesData = $this->productRepo->getProductSalesAggregation(
            $dates['start'],
            $dates['end'],
            $limit * 2 // Get more to allow re-sorting
        );

        $factory = new ProductFactory($salesData);

        return $factory->topByRevenue($limit);
    }

    /**
     * Get top products by profit margin.
     */
    public function getTopProductsByProfit(
        string $period = '7',
        int $limit = 10,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->productRepo->getProductsWithMargins(
            $dates['start'],
            $dates['end'],
            $limit
        )->map(fn ($row) => collect([
            'sku' => $row->sku,
            'title' => $row->title,
            'quantity' => (int) $row->total_quantity,
            'revenue' => (float) $row->total_revenue,
            'cost' => (float) $row->total_cost,
            'profit' => (float) $row->total_profit,
            'margin' => $row->margin_percentage,
        ]));
    }

    /**
     * Get category breakdown.
     */
    public function getCategoryAnalysis(
        string $period = '7',
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->productRepo->getCategoryPerformance(
            $dates['start'],
            $dates['end']
        )->map(fn ($row) => collect([
            'category' => $row->category,
            'product_count' => (int) $row->product_count,
            'total_quantity' => (int) $row->total_quantity,
            'total_revenue' => (float) $row->total_revenue,
        ]));
    }

    /**
     * Get margin report for products with cost data.
     */
    public function getMarginReport(
        string $period = '7',
        int $limit = 50,
        ?string $customFrom = null,
        ?string $customTo = null
    ): Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        return $this->productRepo->getProductsWithMargins(
            $dates['start'],
            $dates['end'],
            $limit
        );
    }

    /**
     * Get low stock alerts.
     */
    public function getLowStockAlerts(int $limit = 20): Collection
    {
        return $this->productRepo->getLowStockProducts($limit);
    }

    /**
     * Get out of stock products.
     */
    public function getOutOfStockProducts(int $limit = 20): Collection
    {
        return $this->productRepo->getOutOfStockProducts($limit);
    }

    /**
     * Get single product performance.
     */
    public function getProductPerformance(
        string $sku,
        string $period = '7',
        ?string $customFrom = null,
        ?string $customTo = null
    ): ?Collection {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        $performance = $this->productRepo->getProductPerformance(
            $sku,
            $dates['start'],
            $dates['end']
        );

        if (! $performance) {
            return null;
        }

        $dailySales = $this->productRepo->getProductDailySales(
            $sku,
            $dates['start'],
            $dates['end']
        );

        $channelBreakdown = $this->productRepo->getProductChannelBreakdown(
            $sku,
            $dates['start'],
            $dates['end']
        );

        // Calculate margin if purchase price exists
        $margin = null;
        if ($performance->purchase_price && $performance->purchase_price > 0) {
            $avgSellingPrice = $performance->total_quantity > 0
                ? $performance->total_revenue / $performance->total_quantity
                : 0;

            if ($avgSellingPrice > 0) {
                $margin = (($avgSellingPrice - $performance->purchase_price) / $avgSellingPrice) * 100;
            }
        }

        return collect([
            'sku' => $performance->sku,
            'title' => $performance->title,
            'purchase_price' => $performance->purchase_price,
            'retail_price' => $performance->retail_price,
            'total_quantity' => (int) $performance->total_quantity,
            'total_revenue' => (float) $performance->total_revenue,
            'total_cost' => (float) $performance->total_cost,
            'order_count' => (int) $performance->order_count,
            'avg_selling_price' => (float) $performance->avg_selling_price,
            'margin_percentage' => $margin ? round($margin, 2) : null,
            'daily_sales' => $dailySales,
            'channel_breakdown' => $channelBreakdown,
        ]);
    }

    /**
     * Get product summary metrics for a period.
     *
     * @return array<string, mixed>
     */
    public function getProductSummary(
        string $period = '7',
        ?string $customFrom = null,
        ?string $customTo = null
    ): array {
        $dates = (new CalculatePeriodDates)($period, $customFrom, $customTo);

        $salesData = $this->productRepo->getProductSalesAggregation(
            $dates['start'],
            $dates['end'],
            1000 // Get all for summary calculations
        );

        $factory = new ProductFactory($salesData);

        return $factory->summary();
    }

    /**
     * Get available categories.
     */
    public function getCategories(): Collection
    {
        return $this->productRepo->getCategories();
    }
}
