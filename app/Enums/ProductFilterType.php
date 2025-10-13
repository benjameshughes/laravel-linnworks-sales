<?php

namespace App\Enums;

enum ProductFilterType: string
{
    case PROFIT_MARGIN = 'profit-margin';
    case SALES_VELOCITY = 'sales-velocity';
    case GROWTH_RATE = 'growth-rate';
    case REVENUE_TIER = 'revenue-tier';
    case STOCK_STATUS = 'stock-status';
    case PRODUCT_AGE = 'product-age';
    case BADGE_TYPE = 'badge-type';
    case CATEGORY = 'category';
    case PERFORMANCE_SCORE = 'performance-score';

    public function label(): string
    {
        return match ($this) {
            self::PROFIT_MARGIN => 'Profit Margin',
            self::SALES_VELOCITY => 'Sales Velocity',
            self::GROWTH_RATE => 'Growth Rate',
            self::REVENUE_TIER => 'Revenue Tier',
            self::STOCK_STATUS => 'Stock Status',
            self::PRODUCT_AGE => 'Product Age',
            self::BADGE_TYPE => 'Badge Type',
            self::CATEGORY => 'Category',
            self::PERFORMANCE_SCORE => 'Performance Score',
        };
    }

    public function getOptions(): array
    {
        return match ($this) {
            self::PROFIT_MARGIN => [
                'low' => ['label' => 'Low (0-10%)', 'min' => 0, 'max' => 10],
                'medium' => ['label' => 'Medium (10-25%)', 'min' => 10, 'max' => 25],
                'high' => ['label' => 'High (25-40%)', 'min' => 25, 'max' => 40],
                'premium' => ['label' => 'Premium (40%+)', 'min' => 40, 'max' => null],
            ],
            self::SALES_VELOCITY => [
                'slow' => ['label' => 'Slow (0-0.5/day)', 'min' => 0, 'max' => 0.5],
                'moderate' => ['label' => 'Moderate (0.5-2/day)', 'min' => 0.5, 'max' => 2],
                'fast' => ['label' => 'Fast (2-5/day)', 'min' => 2, 'max' => 5],
                'very-fast' => ['label' => 'Very Fast (5+/day)', 'min' => 5, 'max' => null],
            ],
            self::GROWTH_RATE => [
                'declining' => ['label' => 'Declining (-100% to -20%)', 'min' => -100, 'max' => -20],
                'stable' => ['label' => 'Stable (-20% to +20%)', 'min' => -20, 'max' => 20],
                'growing' => ['label' => 'Growing (20% to 100%)', 'min' => 20, 'max' => 100],
                'surging' => ['label' => 'Surging (100%+)', 'min' => 100, 'max' => null],
            ],
            self::REVENUE_TIER => [
                'low' => ['label' => 'Low (£0-100)', 'min' => 0, 'max' => 100],
                'medium' => ['label' => 'Medium (£100-500)', 'min' => 100, 'max' => 500],
                'high' => ['label' => 'High (£500-2000)', 'min' => 500, 'max' => 2000],
                'top' => ['label' => 'Top (£2000+)', 'min' => 2000, 'max' => null],
            ],
            self::STOCK_STATUS => [
                'out-of-stock' => ['label' => 'Out of Stock', 'condition' => 'stock_level', 'operator' => '<=', 'value' => 0],
                'low-stock' => ['label' => 'Low Stock', 'condition' => 'low_stock_alert', 'operator' => '=', 'value' => true],
                'in-stock' => ['label' => 'In Stock', 'condition' => 'stock_level', 'operator' => '>', 'value' => 0],
            ],
            self::PRODUCT_AGE => [
                'new' => ['label' => 'New (0-30 days)', 'min' => 0, 'max' => 30],
                'recent' => ['label' => 'Recent (30-90 days)', 'min' => 30, 'max' => 90],
                'established' => ['label' => 'Established (90-365 days)', 'min' => 90, 'max' => 365],
                'mature' => ['label' => 'Mature (365+ days)', 'min' => 365, 'max' => null],
            ],
            self::BADGE_TYPE => collect(ProductBadgeType::cases())
                ->mapWithKeys(fn (ProductBadgeType $badge) => [
                    $badge->value => ['label' => $badge->label(), 'badge' => $badge],
                ])
                ->toArray(),
            self::PERFORMANCE_SCORE => [
                'poor' => ['label' => 'Poor (0-25)', 'min' => 0, 'max' => 25],
                'fair' => ['label' => 'Fair (25-50)', 'min' => 25, 'max' => 50],
                'good' => ['label' => 'Good (50-75)', 'min' => 50, 'max' => 75],
                'excellent' => ['label' => 'Excellent (75-100)', 'min' => 75, 'max' => 100],
            ],
            self::CATEGORY => [], // Dynamic - loaded from database
        };
    }

    public function getDefaultValue(): mixed
    {
        return match ($this) {
            self::PROFIT_MARGIN => 'medium',
            self::SALES_VELOCITY => 'moderate',
            self::GROWTH_RATE => 'stable',
            self::REVENUE_TIER => 'medium',
            self::STOCK_STATUS => 'in-stock',
            self::PRODUCT_AGE => 'established',
            self::BADGE_TYPE => ProductBadgeType::HOT_SELLER->value,
            self::PERFORMANCE_SCORE => 'good',
            self::CATEGORY => null,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::PROFIT_MARGIN => 'Filter products by profit margin percentage',
            self::SALES_VELOCITY => 'Filter by average daily sales volume',
            self::GROWTH_RATE => 'Filter by growth trend compared to previous period',
            self::REVENUE_TIER => 'Filter by total revenue generated',
            self::STOCK_STATUS => 'Filter by current stock availability',
            self::PRODUCT_AGE => 'Filter by how long the product has been in the system',
            self::BADGE_TYPE => 'Filter by performance badge type',
            self::CATEGORY => 'Filter by product category',
            self::PERFORMANCE_SCORE => 'Filter by overall performance score',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PROFIT_MARGIN => 'currency-pound',
            self::SALES_VELOCITY => 'bolt',
            self::GROWTH_RATE => 'trending-up',
            self::REVENUE_TIER => 'chart-bar',
            self::STOCK_STATUS => 'archive-box',
            self::PRODUCT_AGE => 'clock',
            self::BADGE_TYPE => 'star',
            self::CATEGORY => 'tag',
            self::PERFORMANCE_SCORE => 'trophy',
        };
    }

    public function allowsMultipleSelection(): bool
    {
        return match ($this) {
            self::BADGE_TYPE, self::CATEGORY, self::STOCK_STATUS => true,
            default => false,
        };
    }
}
