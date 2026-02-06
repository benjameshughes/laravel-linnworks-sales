<?php

declare(strict_types=1);

use App\Models\Order;
use App\Services\Metrics\Sales\SalesMetrics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('SalesMetrics', function () {
    it('returns metrics summary with correct structure', function () {
        Order::factory()
            ->count(5)
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 2],
            ])
            ->create([
                'received_at' => now()->subDays(3),
                'total_charge' => 100.00,
            ]);

        $service = app(SalesMetrics::class);
        $summary = $service->getMetricsSummary('7', 'all');

        expect($summary)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveKeys(['total_revenue', 'total_orders', 'average_order_value', 'total_items', 'orders_per_day'])
            ->and($summary['total_revenue'])
            ->toBe(500.00)
            ->and($summary['total_orders'])
            ->toBe(5)
            ->and($summary['average_order_value'])
            ->toBe(100.00)
            ->and($summary['total_items'])
            ->toBe(10)
            ->and($summary['orders_per_day'])
            ->toBeGreaterThan(0);
    });

    it('filters metrics by channel', function () {
        Order::factory()->count(3)->create([
            'received_at' => now()->subDays(3),
            'source' => 'amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(2)->create([
            'received_at' => now()->subDays(3),
            'source' => 'ebay',
            'total_charge' => 50.00,
        ]);

        $service = app(SalesMetrics::class);
        $summary = $service->getMetricsSummary('7', 'amazon');

        expect($summary['total_orders'])->toBe(3)
            ->and($summary['total_revenue'])->toBe(300.00);
    });

    it('calculates orders per day correctly', function () {
        Order::factory()->count(10)->create([
            'received_at' => now()->subDays(3),
        ]);

        $service = app(SalesMetrics::class);
        $summary = $service->getMetricsSummary('7');

        expect($summary['orders_per_day'])->toBe(10 / 7);
    });

    it('handles custom date range in metrics summary', function () {
        Order::factory()->count(5)->create([
            'received_at' => Carbon::parse('2025-01-05'),
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(3)->create([
            'received_at' => Carbon::parse('2025-01-20'),
            'total_charge' => 100.00,
        ]);

        $service = app(SalesMetrics::class);
        $summary = $service->getMetricsSummary('custom', 'all', '2025-01-01', '2025-01-10');

        expect($summary['total_orders'])->toBe(5)
            ->and($summary['total_revenue'])->toBe(500.00);
    });

    it('returns top channels sorted by revenue', function () {
        Order::factory()->count(3)->create([
            'received_at' => now()->subDays(3),
            'source' => 'amazon',
            'subsource' => null,
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(2)->create([
            'received_at' => now()->subDays(3),
            'source' => 'ebay',
            'subsource' => null,
            'total_charge' => 150.00,
        ]);

        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'source' => 'website',
            'subsource' => null,
            'total_charge' => 50.00,
        ]);

        $service = app(SalesMetrics::class);
        $topChannels = $service->getTopChannels('7', 'all');

        expect($topChannels)->toHaveCount(3);

        // Verify we have the right channels and revenues (order may vary)
        $channels = $topChannels->pluck('revenue', 'channel')->all();
        expect($channels)->toHaveKey('amazon')
            ->and($channels)->toHaveKey('ebay')
            ->and($channels)->toHaveKey('website')
            ->and($channels['amazon'])->toBe(300.00)
            ->and($channels['ebay'])->toBe(300.00)
            ->and($channels['website'])->toBe(50.00);
    });

    it('returns all channels without limit', function () {
        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'source' => 'amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'source' => 'ebay',
            'total_charge' => 90.00,
        ]);

        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'source' => 'website',
            'total_charge' => 80.00,
        ]);

        $service = app(SalesMetrics::class);
        $topChannels = $service->getTopChannels('7', 'all');

        expect($topChannels)->toHaveCount(3);
    });

    it('returns top products sorted by quantity', function () {
        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 10],
                ['sku' => 'DEF456', 'quantity' => 5],
            ])
            ->create([
                'received_at' => now()->subDays(3),
            ]);

        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 5],
                ['sku' => 'GHI789', 'quantity' => 3],
            ])
            ->create([
                'received_at' => now()->subDays(3),
            ]);

        $service = app(SalesMetrics::class);
        $topProducts = $service->getTopProducts('7', 'all', 10);

        expect($topProducts)
            ->toHaveCount(3)
            ->and($topProducts[0]['sku'])
            ->toBe('ABC123')
            ->and($topProducts[0]['quantity'])
            ->toBe(15)
            ->and($topProducts[1]['sku'])
            ->toBe('DEF456')
            ->and($topProducts[1]['quantity'])
            ->toBe(5);
    });

    it('filters top products by channel', function () {
        Order::factory()
            ->withItems([
                ['sku' => 'ABC123', 'quantity' => 10],
            ])
            ->create([
                'received_at' => now()->subDays(3),
                'source' => 'amazon',
            ]);

        Order::factory()
            ->withItems([
                ['sku' => 'DEF456', 'quantity' => 20],
            ])
            ->create([
                'received_at' => now()->subDays(3),
                'source' => 'ebay',
            ]);

        $service = app(SalesMetrics::class);
        $topProducts = $service->getTopProducts('7', 'amazon', 10);

        expect($topProducts)
            ->toHaveCount(1)
            ->and($topProducts[0]['sku'])
            ->toBe('ABC123');
    });

    it('gets recent orders with default limit', function () {
        Order::factory()->count(20)->create([
            'received_at' => now()->subHour(),
        ]);

        $service = app(SalesMetrics::class);
        $recentOrders = $service->getRecentOrders();

        expect($recentOrders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(15);
    });

    it('gets recent orders with custom limit', function () {
        Order::factory()->count(20)->create([
            'received_at' => now()->subHour(),
        ]);

        $service = app(SalesMetrics::class);
        $recentOrders = $service->getRecentOrders(5);

        expect($recentOrders)->toHaveCount(5);
    });

    it('returns daily revenue data with correct structure', function () {
        Order::factory()
            ->withItems([['sku' => 'ABC', 'quantity' => 2]])
            ->create([
                'received_at' => now()->subDays(2),
                'total_charge' => 100.00,
            ]);

        $service = app(SalesMetrics::class);
        $dailyData = $service->getDailyRevenueData('7');

        expect($dailyData)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(7);

        $dailyData->each(function ($day) {
            expect($day)->toHaveKeys(['date', 'iso_date', 'day', 'revenue', 'orders', 'items', 'avg_order_value']);
        });
    });

    it('calculates positive growth rate', function () {
        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'total_charge' => 200.00,
        ]);

        Order::factory()->create([
            'received_at' => now()->subDays(10),
            'total_charge' => 100.00,
        ]);

        $service = app(SalesMetrics::class);
        $growthRate = $service->growthRate(7);

        expect($growthRate)->toBe(100.0);
    });

    it('calculates negative growth rate', function () {
        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'total_charge' => 50.00,
        ]);

        Order::factory()->create([
            'received_at' => now()->subDays(10),
            'total_charge' => 100.00,
        ]);

        $service = app(SalesMetrics::class);
        $growthRate = $service->growthRate(7);

        expect($growthRate)->toBe(-50.0);
    });

    it('handles zero previous revenue in growth rate', function () {
        Order::factory()->create([
            'received_at' => now()->subDays(3),
            'total_charge' => 100.00,
        ]);

        $service = app(SalesMetrics::class);
        $growthRate = $service->growthRate(7);

        expect($growthRate)->toBe(100.0);
    });

    it('returns zero metrics for empty dataset', function () {
        $service = app(SalesMetrics::class);
        $summary = $service->getMetricsSummary('7');

        expect($summary['total_revenue'])->toBe(0.0)
            ->and($summary['total_orders'])->toBe(0)
            ->and($summary['total_items'])->toBe(0);
    });

    it('handles custom period in top channels', function () {
        Order::factory()->create([
            'received_at' => Carbon::parse('2025-01-05'),
            'source' => 'amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'received_at' => Carbon::parse('2025-01-20'),
            'source' => 'amazon',
            'total_charge' => 200.00,
        ]);

        $service = app(SalesMetrics::class);
        $topChannels = $service->getTopChannels('custom', 'all', '2025-01-01', '2025-01-10');

        expect($topChannels)
            ->toHaveCount(1)
            ->and($topChannels[0]['revenue'])
            ->toBe(100.00);
    });
});
