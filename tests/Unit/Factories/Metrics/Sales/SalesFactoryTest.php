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
            (object) ['items' => [
                ['sku' => 'ABC123', 'quantity' => 2],
                ['sku' => 'DEF456', 'quantity' => 3],
            ]],
            (object) ['items' => [
                ['sku' => 'GHI789', 'quantity' => 5],
            ]],
            (object) ['items' => [
                ['sku' => 'JKL012', 'quantity' => 1],
            ]],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalItemsSold())->toBe(11);
    });

    it('returns zero items when no items in orders', function () {
        $orders = collect([
            (object) ['items' => []],
            (object) ['items' => null],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalItemsSold())->toBe(0);
    });

    it('groups and sorts top channels correctly', function () {
        $orders = collect([
            (object) ['channel_name' => 'Amazon', 'total_charge' => 500.00],
            (object) ['channel_name' => 'eBay', 'total_charge' => 200.00],
            (object) ['channel_name' => 'Amazon', 'total_charge' => 300.00],
            (object) ['channel_name' => 'Website', 'total_charge' => 150.00],
            (object) ['channel_name' => 'eBay', 'total_charge' => 100.00],
        ]);

        $factory = new SalesFactory($orders);
        $topChannels = $factory->topChannels(3);

        expect($topChannels)
            ->toHaveCount(3)
            ->and($topChannels[0]['source'])
            ->toBe('Amazon')
            ->and($topChannels[0]['revenue'])
            ->toBe(800.00)
            ->and($topChannels[0]['order_count'])
            ->toBe(2)
            ->and($topChannels[1]['source'])
            ->toBe('eBay')
            ->and($topChannels[1]['revenue'])
            ->toBe(300.00)
            ->and($topChannels[1]['order_count'])
            ->toBe(2)
            ->and($topChannels[2]['source'])
            ->toBe('Website')
            ->and($topChannels[2]['revenue'])
            ->toBe(150.00)
            ->and($topChannels[2]['order_count'])
            ->toBe(1);
    });

    it('limits top channels correctly', function () {
        $orders = collect([
            (object) ['channel_name' => 'Amazon', 'total_charge' => 500.00],
            (object) ['channel_name' => 'eBay', 'total_charge' => 400.00],
            (object) ['channel_name' => 'Website', 'total_charge' => 300.00],
            (object) ['channel_name' => 'Etsy', 'total_charge' => 200.00],
        ]);

        $factory = new SalesFactory($orders);
        $topChannels = $factory->topChannels(2);

        expect($topChannels)->toHaveCount(2)
            ->and($topChannels[0]['source'])->toBe('Amazon')
            ->and($topChannels[1]['source'])->toBe('eBay');
    });

    it('groups and sorts top products correctly', function () {
        $orders = collect([
            (object) ['items' => [
                ['sku' => 'ABC123', 'quantity' => 5],
                ['sku' => 'DEF456', 'quantity' => 2],
            ]],
            (object) ['items' => [
                ['sku' => 'ABC123', 'quantity' => 3],
                ['sku' => 'GHI789', 'quantity' => 10],
            ]],
            (object) ['items' => [
                ['sku' => 'DEF456', 'quantity' => 1],
            ]],
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
            (object) ['items' => [
                ['sku' => 'SKU1', 'quantity' => 100],
                ['sku' => 'SKU2', 'quantity' => 90],
                ['sku' => 'SKU3', 'quantity' => 80],
                ['sku' => 'SKU4', 'quantity' => 70],
            ]],
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
            (object) ['is_processed' => 1],
            (object) ['is_processed' => 0],
            (object) ['is_processed' => true],
            (object) ['is_processed' => false],
            (object) ['is_processed' => 1],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalProcessedOrders())->toBe(3.0);
    });

    it('calculates total open orders correctly', function () {
        $orders = collect([
            (object) ['is_processed' => 0],
            (object) ['is_processed' => 1],
            (object) ['is_processed' => false],
            (object) ['is_processed' => true],
            (object) ['is_processed' => 0],
        ]);

        $factory = new SalesFactory($orders);

        expect($factory->totalOpenOrders())->toBe(3.0);
    });

    it('calculates processed orders revenue correctly', function () {
        $orders = collect([
            (object) ['is_processed' => 1, 'total_charge' => 100.00],
            (object) ['is_processed' => 0, 'total_charge' => 50.00],
            (object) ['is_processed' => 1, 'total_charge' => 200.00],
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
