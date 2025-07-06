<?php

namespace App\Console\Commands;

use App\Services\ProductAnalyticsService;
use Illuminate\Console\Command;

class DebugAnalytics extends Command
{
    protected $signature = 'debug:analytics';
    protected $description = 'Debug analytics service results';

    public function handle(): int
    {
        $service = app(ProductAnalyticsService::class);
        
        $this->info('=== ProductAnalyticsService Debug ===');
        
        // Test metrics
        $metrics = $service->getMetrics(30);
        $this->info('Metrics for 30 days:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['total_products', $metrics['total_products']],
                ['total_units_sold', $metrics['total_units_sold']],
                ['total_revenue', $metrics['total_revenue']],
                ['avg_profit_margin', $metrics['avg_profit_margin']],
                ['top_performing_sku', $metrics['top_performing_sku']],
                ['categories_count', $metrics['categories_count']],
                ['low_stock_count', $metrics['low_stock_count']],
            ]
        );
        
        // Test top selling products
        $topProducts = $service->getTopSellingProducts(30, null, null, 10);
        $this->info("Top selling products count: " . $topProducts->count());
        
        if ($topProducts->isNotEmpty()) {
            $this->info('First few top products:');
            foreach ($topProducts->take(5) as $index => $item) {
                $this->line(($index + 1) . ". {$item['product']->sku} - {$item['product']->title} - Sold: {$item['total_sold']} - Revenue: £{$item['total_revenue']}");
            }
        }
        
        // Test categories
        $categories = $service->getTopCategories(30);
        $this->info("Categories count: " . $categories->count());
        
        return Command::SUCCESS;
    }
}