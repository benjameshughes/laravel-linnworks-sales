<?php

declare(strict_types=1);

use App\Factories\Metrics\Sales\SalesFactory;

describe('SalesFactory', function () {
    it('calculates total revenue correctly', function () {
        $orders = collect([
            (object) ['total_charge' => 100.50],
            (object) ['total_charge' => 200.75],
            (object) ['total_charge' => 50.25],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalRevenue())->toBe(351.50);
    });

    it('returns zero revenue for empty orders', function () {
        $factory = new SalesFactory(collect([]));

        expect($factory->totalRevenue())->toBe(0.0);
    });

    it('calculates total orders correctly', function () {
        $orders = collect([
            (object) ['total_charge' => 100.00],
            (object) ['total_charge' => 200.00],
            (object) ['total_charge' => 50.00],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalOrders())->toBe(3);
    });

    it('returns zero for total orders when empty', function () {
        $factory = new SalesFactory(collect([]));

        expect($factory->totalOrders())->toBe(0);
    });

    it('calculates average order value correctly', function () {
        $orders = collect([
            (object) ['total_charge' => 100.00],
            (object) ['total_charge' => 200.00],
            (object) ['total_charge' => 300.00],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->averageOrderValue())->toBe(200.00);
    });

    it('returns zero for average order value when empty', function () {
        $factory = new SalesFactory(collect([]));

        expect($factory->averageOrderValue())->toBe(0.0);
    });

    it('calculates total items sold correctly', function () {
        $orders = collect([
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'ABC123', 'quantity' => 2],
                    (object) ['sku' => 'DEF456', 'quantity' => 3],
                ]),
            ],
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'GHI789', 'quantity' => 5],
                ]),
            ],
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'JKL012', 'quantity' => 1],
                ]),
            ],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalItemsSold())->toBe(11);
    });

    it('returns zero items when no items in orders', function () {
        $orders = collect([
            (object) ['orderItems' => collect([])],
            (object) ['orderItems' => collect([])],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalItemsSold())->toBe(0);
    });

    it('groups and sorts top channels correctly', function () {
        $orders = collect([
            (object) ['source' => 'Amazon', 'subsource' => 'Store1', 'total_charge' => 500.00],
            (object) ['source' => 'eBay', 'subsource' => 'Shop1', 'total_charge' => 200.00],
            (object) ['source' => 'Amazon', 'subsource' => 'Store1', 'total_charge' => 300.00],
            (object) ['source' => 'Website', 'subsource' => null, 'total_charge' => 150.00],
            (object) ['source' => 'eBay', 'subsource' => 'Shop1', 'total_charge' => 100.00],
        ]);

        $factory = new SalesFactory($orders);
        $topChannels = $factory->topChannels(3);

        expect($topChannels)
            ->toHaveCount(3)
            ->and($topChannels[0]['channel'])
            ->toBe('Amazon')
            ->and($topChannels[0]['name'])
            ->toBe('Store1 (Amazon)')
            ->and($topChannels[0]['revenue'])
            ->toBe(800.00)
            ->and($topChannels[0]['orders'])
            ->toBe(2)
            ->and($topChannels[1]['channel'])
            ->toBe('eBay')
            ->and($topChannels[1]['revenue'])
            ->toBe(300.00)
            ->and($topChannels[1]['orders'])
            ->toBe(2)
            ->and($topChannels[2]['channel'])
            ->toBe('Website')
            ->and($topChannels[2]['name'])
            ->toBe('Website')
            ->and($topChannels[2]['revenue'])
            ->toBe(150.00)
            ->and($topChannels[2]['orders'])
            ->toBe(1);
    });

    it('limits top channels correctly', function () {
        $orders = collect([
            (object) ['source' => 'Amazon', 'subsource' => null, 'total_charge' => 500.00],
            (object) ['source' => 'eBay', 'subsource' => null, 'total_charge' => 400.00],
            (object) ['source' => 'Website', 'subsource' => null, 'total_charge' => 300.00],
            (object) ['source' => 'Etsy', 'subsource' => null, 'total_charge' => 200.00],
        ]);

        $factory = new SalesFactory($orders);
        $topChannels = $factory->topChannels(2);

        expect($topChannels)->toHaveCount(2)
            ->and($topChannels[0]['channel'])->toBe('Amazon')
            ->and($topChannels[1]['channel'])->toBe('eBay');
    });

    it('groups and sorts top products correctly', function () {
        $orders = collect([
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'ABC123', 'quantity' => 5, 'item_title' => 'Product A', 'line_total' => 50.00],
                    (object) ['sku' => 'DEF456', 'quantity' => 2, 'item_title' => 'Product B', 'line_total' => 20.00],
                ]),
            ],
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'ABC123', 'quantity' => 3, 'item_title' => 'Product A', 'line_total' => 30.00],
                    (object) ['sku' => 'GHI789', 'quantity' => 10, 'item_title' => 'Product C', 'line_total' => 100.00],
                ]),
            ],
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'DEF456', 'quantity' => 1, 'item_title' => 'Product B', 'line_total' => 10.00],
                ]),
            ],
        ]);

        $factory = new SalesFactory($orders);
        $topProducts = $factory->topProducts(10);

        expect($topProducts)
            ->toHaveCount(3)
            ->and($topProducts[0]['sku'])
            ->toBe('GHI789')
            ->and($topProducts[0]['quantity'])
            ->toBe(10)
            ->and($topProducts[1]['sku'])
            ->toBe('ABC123')
            ->and($topProducts[1]['quantity'])
            ->toBe(8)
            ->and($topProducts[2]['sku'])
            ->toBe('DEF456')
            ->and($topProducts[2]['quantity'])
            ->toBe(3);
    });

    it('limits top products correctly', function () {
        $orders = collect([
            (object) [
                'orderItems' => collect([
                    (object) ['sku' => 'SKU1', 'quantity' => 100, 'item_title' => 'Prod 1', 'line_total' => 1000.00],
                    (object) ['sku' => 'SKU2', 'quantity' => 90, 'item_title' => 'Prod 2', 'line_total' => 900.00],
                    (object) ['sku' => 'SKU3', 'quantity' => 80, 'item_title' => 'Prod 3', 'line_total' => 800.00],
                    (object) ['sku' => 'SKU4', 'quantity' => 70, 'item_title' => 'Prod 4', 'line_total' => 700.00],
                ]),
            ],
        ]);

        $factory = new SalesFactory($orders);
        $topProducts = $factory->topProducts(2);

        expect($topProducts)->toHaveCount(2)
            ->and($topProducts[0]['sku'])->toBe('SKU1')
            ->and($topProducts[1]['sku'])->toBe('SKU2');
    });

    it('calculates positive growth rate correctly', function () {
        $currentOrders = collect([
            (object) ['total_charge' => 200.00],
        ]);

        $previousOrders = collect([
            (object) ['total_charge' => 100.00],
        ]);

        $currentFactory = new SalesFactory($currentOrders);
        $previousFactory = new SalesFactory($previousOrders);

        $growthRate = $currentFactory->growthRate($previousFactory);

        expect($growthRate)->toBe(100.0);
    });

    it('calculates negative growth rate correctly', function () {
        $currentOrders = collect([
            (object) ['total_charge' => 50.00],
        ]);

        $previousOrders = collect([
            (object) ['total_charge' => 100.00],
        ]);

        $currentFactory = new SalesFactory($currentOrders);
        $previousFactory = new SalesFactory($previousOrders);

        $growthRate = $currentFactory->growthRate($previousFactory);

        expect($growthRate)->toBe(-50.0);
    });

    it('handles zero previous revenue with current revenue', function () {
        $currentOrders = collect([
            (object) ['total_charge' => 100.00],
        ]);

        $previousOrders = collect([]);

        $currentFactory = new SalesFactory($currentOrders);
        $previousFactory = new SalesFactory($previousOrders);

        $growthRate = $currentFactory->growthRate($previousFactory);

        expect($growthRate)->toBe(100.0);
    });

    it('handles zero previous revenue with zero current revenue', function () {
        $currentOrders = collect([]);
        $previousOrders = collect([]);

        $currentFactory = new SalesFactory($currentOrders);
        $previousFactory = new SalesFactory($previousOrders);

        $growthRate = $currentFactory->growthRate($previousFactory);

        expect($growthRate)->toBe(0.0);
    });

    it('calculates total processed orders correctly', function () {
        $orders = collect([
            (object) ['status' => 1],
            (object) ['status' => 0],
            (object) ['status' => 1],
            (object) ['status' => 0],
            (object) ['status' => 1],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalProcessedOrders())->toBe(3.0);
    });

    it('calculates total open orders correctly', function () {
        $orders = collect([
            (object) ['status' => 0],
            (object) ['status' => 1],
            (object) ['status' => 0],
            (object) ['status' => 1],
            (object) ['status' => 0],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalOpenOrders())->toBe(3.0);
    });

    it('calculates processed orders revenue correctly', function () {
        $orders = collect([
            (object) ['status' => 1, 'total_charge' => 100.00],
            (object) ['status' => 0, 'total_charge' => 50.00],
            (object) ['status' => 1, 'total_charge' => 200.00],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->processedOrdersRevenue())->toBe(300.00);
    });

    it('handles empty orders for top channels', function () {
        $factory = new SalesFactory(collect([]));
        $topChannels = $factory->topChannels(3);

        expect($topChannels)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(0);
    });

    it('handles empty orders for top products', function () {
        $factory = new SalesFactory(collect([]));
        $topProducts = $factory->topProducts(10);

        expect($topProducts)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(0);
    });
});
