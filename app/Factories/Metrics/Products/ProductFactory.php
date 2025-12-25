<?php

declare(strict_types=1);

namespace App\Factories\Metrics\Products;

use Illuminate\Support\Collection;

/**
 * Calculation layer for product analytics.
 *
 * Takes pre-aggregated sales data from the repository and performs
 * calculations for product performance metrics.
 */
final class ProductFactory
{
    /**
     * @param  Collection  $salesData  Pre-aggregated sales data from repository
     */
    public function __construct(private readonly Collection $salesData) {}

    /**
     * Get total revenue from all products.
     */
    public function totalRevenue(): float
    {
        return (float) $this->salesData->sum('total_revenue');
    }

    /**
     * Get total units sold.
     */
    public function totalUnitsSold(): int
    {
        return (int) $this->salesData->sum('total_quantity');
    }

    /**
     * Get total cost of goods sold.
     */
    public function totalCost(): float
    {
        return (float) $this->salesData->sum('total_cost');
    }

    /**
     * Get total profit.
     */
    public function totalProfit(): float
    {
        return $this->totalRevenue() - $this->totalCost();
    }

    /**
     * Get average margin percentage.
     */
    public function averageMargin(): float
    {
        $revenue = $this->totalRevenue();

        if ($revenue <= 0) {
            return 0.0;
        }

        return (($revenue - $this->totalCost()) / $revenue) * 100;
    }

    /**
     * Get unique product count.
     */
    public function productCount(): int
    {
        return $this->salesData->unique('sku')->count();
    }

    /**
     * Get average revenue per product.
     */
    public function averageRevenuePerProduct(): float
    {
        $count = $this->productCount();

        return $count > 0 ? $this->totalRevenue() / $count : 0.0;
    }

    /**
     * Get top products by quantity sold.
     */
    public function topByQuantity(int $limit = 10): Collection
    {
        return $this->salesData
            ->sortByDesc('total_quantity')
            ->take($limit)
            ->map(fn ($row) => collect([
                'sku' => $row->sku,
                'title' => $this->getTitle($row),
                'quantity' => (int) $row->total_quantity,
                'revenue' => (float) $row->total_revenue,
                'order_count' => (int) $row->order_count,
            ]))
            ->values();
    }

    /**
     * Get top products by revenue.
     */
    public function topByRevenue(int $limit = 10): Collection
    {
        return $this->salesData
            ->sortByDesc('total_revenue')
            ->take($limit)
            ->map(fn ($row) => collect([
                'sku' => $row->sku,
                'title' => $this->getTitle($row),
                'quantity' => (int) $row->total_quantity,
                'revenue' => (float) $row->total_revenue,
                'order_count' => (int) $row->order_count,
            ]))
            ->values();
    }

    /**
     * Get top products by profit.
     */
    public function topByProfit(int $limit = 10): Collection
    {
        return $this->salesData
            ->filter(fn ($row) => isset($row->total_cost) && $row->total_cost > 0)
            ->map(function ($row) {
                $profit = $row->total_revenue - $row->total_cost;
                $margin = $row->total_revenue > 0
                    ? ($profit / $row->total_revenue) * 100
                    : 0;

                return (object) array_merge((array) $row, [
                    'profit' => $profit,
                    'margin' => $margin,
                ]);
            })
            ->sortByDesc('profit')
            ->take($limit)
            ->map(fn ($row) => collect([
                'sku' => $row->sku,
                'title' => $this->getTitle($row),
                'quantity' => (int) $row->total_quantity,
                'revenue' => (float) $row->total_revenue,
                'cost' => (float) $row->total_cost,
                'profit' => (float) $row->profit,
                'margin' => round($row->margin, 2),
            ]))
            ->values();
    }

    /**
     * Get category breakdown.
     */
    public function categoryBreakdown(): Collection
    {
        return $this->salesData
            ->groupBy('category')
            ->map(function (Collection $items, string $category) {
                return collect([
                    'category' => $category ?: 'Uncategorized',
                    'product_count' => $items->count(),
                    'total_quantity' => $items->sum('total_quantity'),
                    'total_revenue' => $items->sum('total_revenue'),
                ]);
            })
            ->sortByDesc('total_revenue')
            ->values();
    }

    /**
     * Get margin analysis for products with cost data.
     */
    public function marginAnalysis(): Collection
    {
        return $this->salesData
            ->filter(fn ($row) => isset($row->purchase_price) && $row->purchase_price > 0)
            ->map(function ($row) {
                $avgSellingPrice = $row->total_quantity > 0
                    ? $row->total_revenue / $row->total_quantity
                    : 0;

                $margin = $avgSellingPrice > 0
                    ? (($avgSellingPrice - $row->purchase_price) / $avgSellingPrice) * 100
                    : 0;

                return collect([
                    'sku' => $row->sku,
                    'title' => $this->getTitle($row),
                    'purchase_price' => (float) $row->purchase_price,
                    'avg_selling_price' => round($avgSellingPrice, 2),
                    'quantity' => (int) $row->total_quantity,
                    'revenue' => (float) $row->total_revenue,
                    'margin' => round($margin, 2),
                ]);
            })
            ->sortByDesc('margin')
            ->values();
    }

    /**
     * Get products with negative margins (selling below cost).
     */
    public function unprofitableProducts(): Collection
    {
        return $this->marginAnalysis()
            ->filter(fn ($item) => $item['margin'] < 0)
            ->values();
    }

    /**
     * Get a summary of product metrics.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $revenue = $this->totalRevenue();
        $cost = $this->totalCost();
        $profit = $revenue - $cost;

        return [
            'total_revenue' => $revenue,
            'total_units_sold' => $this->totalUnitsSold(),
            'total_cost' => $cost,
            'total_profit' => $profit,
            'average_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
            'product_count' => $this->productCount(),
            'average_revenue_per_product' => round($this->averageRevenuePerProduct(), 2),
        ];
    }

    /**
     * Get the product title from the row.
     */
    private function getTitle(object $row): string
    {
        $title = $row->title ?? 'Unknown Product';

        // Skip if it's the placeholder value
        if ($title === 'Unknown Product' && isset($row->item_title)) {
            $title = $row->item_title;
        }

        return $title ?: 'Unknown Product';
    }
}
