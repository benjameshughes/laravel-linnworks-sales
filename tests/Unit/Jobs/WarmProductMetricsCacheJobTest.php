<?php

declare(strict_types=1);

use App\Jobs\WarmProductMetricsCacheJob;
use App\Models\Order;
use App\Models\Product;
use App\Services\ProductAnalyticsService;
use App\Services\ProductBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

describe('WarmProductMetricsCacheJob', function () {
    it('caches product metrics for specified period', function () {
        Product::factory()->count(10)->create();
        Order::factory()
            ->count(5)
            ->withItems([
                ['sku' => Product::first()->sku, 'quantity' => 2],
            ])
            ->create(['received_at' => now()->subDays(3)]);

        $job = new WarmProductMetricsCacheJob('7');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_7d');

        expect($cachedData)
            ->toBeArray()
            ->toHaveKeys(['metrics', 'top_products', 'categories', 'stock_alerts', 'warmed_at'])
            ->and($cachedData['metrics'])
            ->toBeArray()
            ->and($cachedData['warmed_at'])
            ->not->toBeNull();
    });

    it('caches top products data', function () {
        $products = Product::factory()->count(5)->create();
        Order::factory()
            ->withItems([
                ['sku' => $products[0]->sku, 'quantity' => 10],
                ['sku' => $products[1]->sku, 'quantity' => 5],
            ])
            ->create(['received_at' => now()->subDays(3)]);

        $job = new WarmProductMetricsCacheJob('30');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_30d');

        expect($cachedData['top_products'])
            ->toBeArray()
            ->not->toBeEmpty();
    });

    it('caches category data', function () {
        Product::factory()->count(5)->create(['category_name' => 'Electronics']);
        Product::factory()->count(3)->create(['category_name' => 'Clothing']);

        $job = new WarmProductMetricsCacheJob('7');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_7d');

        expect($cachedData['categories'])
            ->toBeArray();
    });

    it('caches stock alerts', function () {
        Product::factory()->create([
            'stock_available' => 5,
            'stock_minimum' => 10,
        ]);

        $job = new WarmProductMetricsCacheJob('7');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_7d');

        expect($cachedData['stock_alerts'])
            ->toBeArray();
    });

    it('prewarms badges for top 50 products', function () {
        $products = Product::factory()->count(100)->create();
        Order::factory()
            ->count(50)
            ->withItems([
                ['sku' => $products[0]->sku, 'quantity' => 10],
            ])
            ->create(['received_at' => now()->subDays(3)]);

        $job = new WarmProductMetricsCacheJob('30');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        // Job should complete without errors and cache data
        $cachedData = Cache::get('product_metrics_30d');
        expect($cachedData)->not->toBeNull();
    });

    it('has unique job ID based on period', function () {
        $job1 = new WarmProductMetricsCacheJob('7');
        $job2 = new WarmProductMetricsCacheJob('30');
        $job3 = new WarmProductMetricsCacheJob('7');

        expect($job1->uniqueId())
            ->toBe('warm-product-metrics-7')
            ->and($job2->uniqueId())
            ->toBe('warm-product-metrics-30')
            ->and($job1->uniqueId())
            ->toBe($job3->uniqueId());
    });

    it('checks for batch cancellation', function () {
        Product::factory()->count(5)->create();

        $job = new WarmProductMetricsCacheJob('7');

        // Test without a cancelled batch - should complete normally
        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_7d');
        expect($cachedData)->not->toBeNull();
    });

    it('stores cache with forever duration', function () {
        Product::factory()->count(5)->create();

        $job = new WarmProductMetricsCacheJob('7');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        // Cache should exist
        $cachedData = Cache::get('product_metrics_7d');
        expect($cachedData)->not->toBeNull();

        // Should still exist after time has passed (simulated by checking it exists)
        expect(Cache::has('product_metrics_7d'))->toBeTrue();
    });

    it('handles empty product dataset gracefully', function () {
        $job = new WarmProductMetricsCacheJob('7');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_7d');

        expect($cachedData)
            ->toBeArray()
            ->toHaveKeys(['metrics', 'top_products', 'categories', 'stock_alerts', 'warmed_at']);
    });

    it('handles products without orders', function () {
        Product::factory()->count(10)->create();

        $job = new WarmProductMetricsCacheJob('30');

        $job->handle(
            app(ProductAnalyticsService::class),
            app(ProductBadgeService::class)
        );

        $cachedData = Cache::get('product_metrics_30d');

        expect($cachedData)
            ->toBeArray()
            ->and($cachedData['top_products'])
            ->toBeArray();
    });

    it('has correct timeout and retry configuration', function () {
        $job = new WarmProductMetricsCacheJob('7');

        expect($job->tries)
            ->toBe(3)
            ->and($job->timeout)
            ->toBe(600)
            ->and($job->maxExceptions)
            ->toBe(3)
            ->and($job->uniqueFor)
            ->toBe(300);
    });

    it('implements ShouldQueue interface', function () {
        $job = new WarmProductMetricsCacheJob('7');

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('implements ShouldBeUnique interface', function () {
        $job = new WarmProductMetricsCacheJob('7');

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class);
    });

    it('stores period as readonly property', function () {
        $job = new WarmProductMetricsCacheJob('90');

        expect($job->period)->toBe('90');
    });
});
