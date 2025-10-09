<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExtractOrderAnalytics extends Command
{
    protected $signature = 'analytics:extract-orders
                            {--days=30 : Number of days to analyze}
                            {--channel= : Filter by specific channel}';

    protected $description = 'Extract analytics data from orders (pricing, channels, products, quantities)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $channel = $this->option('channel');

        $this->info("Extracting order analytics for last {$days} days...");
        $this->newLine();

        $startDate = now()->subDays($days);

        // Build base query
        $ordersQuery = Order::where('received_date', '>=', $startDate);

        if ($channel) {
            $ordersQuery->where('channel_name', $channel);
            $this->info("Filtered by channel: {$channel}");
        }

        // === OVERVIEW METRICS ===
        $this->info('📊 OVERVIEW METRICS');
        $this->line(str_repeat('=', 50));

        $totalOrders = $ordersQuery->count();
        $totalRevenue = $ordersQuery->sum('total_charge');
        $totalProfit = $ordersQuery->sum('profit_margin');
        $totalTax = $ordersQuery->sum('tax');
        $totalPostage = $ordersQuery->sum('postage_cost');
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Calculate profit from order items (where unit_cost is available)
        $itemProfit = DB::table('order_items')
            ->whereIn('order_id', $ordersQuery->pluck('id'))
            ->where('unit_cost', '>', 0)
            ->selectRaw('SUM((price_per_unit - unit_cost) * quantity) as total_profit')
            ->value('total_profit') ?? 0;

        // Data quality metrics
        $itemsWithCost = DB::table('order_items')
            ->whereIn('order_id', $ordersQuery->pluck('id'))
            ->where('unit_cost', '>', 0)
            ->count();
        $totalItems = DB::table('order_items')
            ->whereIn('order_id', $ordersQuery->pluck('id'))
            ->count();
        $costDataCoverage = $totalItems > 0 ? ($itemsWithCost / $totalItems) * 100 : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Orders', number_format($totalOrders)],
                ['Total Revenue', '£' . number_format($totalRevenue, 2)],
                ['Total Profit (calculated)', '£' . number_format($itemProfit, 2)],
                ['Total Tax', '£' . number_format($totalTax, 2)],
                ['Total Postage', '£' . number_format($totalPostage, 2)],
                ['Avg Order Value', '£' . number_format($avgOrderValue, 2)],
                ['Profit Margin %', $totalRevenue > 0 ? number_format(($itemProfit / $totalRevenue) * 100, 1) . '%' : '0%'],
                ['Cost Data Coverage', number_format($costDataCoverage, 1) . '% (' . $itemsWithCost . '/' . $totalItems . ' items)'],
            ]
        );

        if ($costDataCoverage < 100) {
            $this->newLine();
            $this->warn('⚠️  Cost data not available for all items. Profit calculations are partial.');
        }

        $this->newLine();

        // === SALES BY CHANNEL ===
        $this->info('📺 SALES BY CHANNEL');
        $this->line(str_repeat('=', 50));

        // Calculate profit by channel from order items
        $channelProfit = DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'orders.channel_name',
                DB::raw('SUM((order_items.price_per_unit - order_items.unit_cost) * order_items.quantity) as profit')
            )
            ->where('orders.received_date', '>=', $startDate)
            ->where('order_items.unit_cost', '>', 0)
            ->when($channel, fn($q) => $q->where('orders.channel_name', $channel))
            ->groupBy('orders.channel_name')
            ->pluck('profit', 'channel_name');

        $channelStats = DB::table('orders')
            ->select(
                'channel_name',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_charge) as revenue'),
                DB::raw('AVG(total_charge) as avg_order_value')
            )
            ->where('received_date', '>=', $startDate)
            ->when($channel, fn($q) => $q->where('channel_name', $channel))
            ->groupBy('channel_name')
            ->orderByDesc('revenue')
            ->get();

        $channelData = $channelStats->map(fn($stat) => [
            $stat->channel_name ?? 'Unknown',
            number_format($stat->order_count),
            '£' . number_format($stat->revenue, 2),
            '£' . number_format($channelProfit[$stat->channel_name] ?? 0, 2),
            '£' . number_format($stat->avg_order_value, 2),
            $stat->revenue > 0 ? number_format((($channelProfit[$stat->channel_name] ?? 0) / $stat->revenue) * 100, 1) . '%' : '0%',
        ]);

        $this->table(
            ['Channel', 'Orders', 'Revenue', 'Profit', 'AOV', 'Margin %'],
            $channelData
        );
        $this->newLine();

        // === TOP SELLING PRODUCTS ===
        $this->info('🏆 TOP 10 SELLING PRODUCTS');
        $this->line(str_repeat('=', 50));

        $orderIds = $ordersQuery->pluck('id');

        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.sku', '=', 'products.sku')
            ->select(
                'order_items.sku',
                'products.title',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue'),
                DB::raw('AVG(order_items.price_per_unit) as avg_price'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as num_orders')
            )
            ->whereIn('order_items.order_id', $orderIds)
            ->groupBy('order_items.sku', 'products.title')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        $productData = $topProducts->map(fn($product) => [
            $product->sku,
            substr($product->title, 0, 30),
            number_format($product->total_quantity),
            '£' . number_format($product->total_revenue, 2),
            '£' . number_format($product->avg_price, 2),
            number_format($product->num_orders),
        ]);

        $this->table(
            ['SKU', 'Product', 'Qty Sold', 'Revenue', 'Avg Price', 'Orders'],
            $productData
        );
        $this->newLine();

        // === DAILY SALES TREND ===
        $this->info('📈 DAILY SALES TREND (Last 7 days)');
        $this->line(str_repeat('=', 50));

        $dailyStats = DB::table('orders')
            ->select(
                DB::raw('DATE(received_date) as date'),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_charge) as revenue')
            )
            ->where('received_date', '>=', now()->subDays(7))
            ->when($channel, fn($q) => $q->where('channel_name', $channel))
            ->groupBy(DB::raw('DATE(received_date)'))
            ->orderBy('date', 'desc')
            ->get();

        $dailyData = $dailyStats->map(fn($day) => [
            $day->date,
            number_format($day->order_count),
            '£' . number_format($day->revenue, 2),
            $day->order_count > 0 ? '£' . number_format($day->revenue / $day->order_count, 2) : '£0.00',
        ]);

        $this->table(
            ['Date', 'Orders', 'Revenue', 'AOV'],
            $dailyData
        );
        $this->newLine();

        // === PRODUCT CATEGORIES ===
        $this->info('📦 TOP PRODUCT CATEGORIES');
        $this->line(str_repeat('=', 50));

        $categoryStats = DB::table('order_items')
            ->join('products', 'order_items.sku', '=', 'products.sku')
            ->select(
                'products.category_name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(CASE WHEN order_items.line_total > 0 THEN order_items.line_total ELSE order_items.quantity * order_items.price_per_unit END) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as num_orders')
            )
            ->whereIn('order_items.order_id', $orderIds)
            ->whereNotNull('products.category_name')
            ->groupBy('products.category_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $categoryData = $categoryStats->map(fn($cat) => [
            $cat->category_name ?? 'Uncategorized',
            number_format($cat->total_quantity),
            '£' . number_format($cat->total_revenue, 2),
            number_format($cat->num_orders),
        ]);

        $this->table(
            ['Category', 'Qty Sold', 'Revenue', 'Orders'],
            $categoryData
        );
        $this->newLine();

        // === ORDER STATUS BREAKDOWN ===
        $this->info('📋 ORDER STATUS');
        $this->line(str_repeat('=', 50));

        $openOrders = Order::where('is_open', true)
            ->where('received_date', '>=', $startDate)
            ->when($channel, fn($q) => $q->where('channel_name', $channel))
            ->count();

        $processedOrders = Order::where('is_processed', true)
            ->where('received_date', '>=', $startDate)
            ->when($channel, fn($q) => $q->where('channel_name', $channel))
            ->count();

        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Open Orders', number_format($openOrders), $totalOrders > 0 ? number_format(($openOrders / $totalOrders) * 100, 1) . '%' : '0%'],
                ['Processed Orders', number_format($processedOrders), $totalOrders > 0 ? number_format(($processedOrders / $totalOrders) * 100, 1) . '%' : '0%'],
            ]
        );

        $this->newLine();
        $this->info('✅ Analytics extraction complete!');

        // Show note about improving cost data
        if ($costDataCoverage < 100) {
            $this->newLine();
            $this->comment('💡 TO IMPROVE PROFIT ACCURACY:');
            $this->line('   • Add purchase prices in Linnworks for products');
            $this->line('   • Ensure order items have cost data when syncing');
            $this->line('   • Current coverage: ' . number_format($costDataCoverage, 1) . '% of items');
        }

        return self::SUCCESS;
    }
}
