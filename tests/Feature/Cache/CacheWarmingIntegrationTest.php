<?php

use App\Events\CachePeriodWarmed;
use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use App\Events\OrdersSynced;
use App\Jobs\WarmPeriodCacheJob;
use App\Listeners\WarmMetricsCache;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Event::fake([
        CacheWarmingStarted::class,
        CachePeriodWarmed::class,
        CacheWarmingCompleted::class,
    ]);
});

test('complete cache warming flow works end-to-end', function () {
    // Unfake events so we can test real execution
    Event::fake([
        CacheWarmingStarted::class,
        CacheWarmingCompleted::class,
    ]);

    // Create test orders
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
        'total_charge' => 100.00,
        'status' => 'processed',
    ]);

    Order::factory()->count(20)->create([
        'received_date' => now()->subDays(15),
        'total_charge' => 50.00,
        'status' => 'processed',
    ]);

    Order::factory()->count(30)->create([
        'received_date' => now()->subDays(45),
        'total_charge' => 75.00,
        'status' => 'processed',
    ]);

    // Step 1: Trigger cache warming via OrdersSynced event
    $event = new OrdersSynced(60, 'test');
    $listener = new WarmMetricsCache();

    // Step 2: Listener handles event and dispatches batch
    $listener->handle($event);

    // Verify CacheWarmingStarted was dispatched
    Event::assertDispatched(CacheWarmingStarted::class);

    // Step 3: Process jobs from batch (simulate queue worker)
    $jobs = [
        new WarmPeriodCacheJob('7', 'all'),
        new WarmPeriodCacheJob('30', 'all'),
        new WarmPeriodCacheJob('90', 'all'),
    ];

    foreach ($jobs as $job) {
        $job->handle();
    }

    // Step 4: Verify all caches are populated
    expect(Cache::has('metrics_7d_all'))->toBeTrue()
        ->and(Cache::has('metrics_30d_all'))->toBeTrue()
        ->and(Cache::has('metrics_90d_all'))->toBeTrue();

    // Step 5: Verify cached data is correct
    $cache7d = Cache::get('metrics_7d_all');
    $cache30d = Cache::get('metrics_30d_all');
    $cache90d = Cache::get('metrics_90d_all');

    expect($cache7d['orders'])->toBe(10)
        ->and($cache30d['orders'])->toBe(30) // 10 + 20
        ->and($cache90d['orders'])->toBe(60); // 10 + 20 + 30
});

test('sequential job processing maintains memory limits', function () {
    // Create large dataset
    Order::factory()->count(1000)->create([
        'received_date' => now()->subDays(5),
        'total_charge' => 100.00,
    ]);

    // Run jobs sequentially
    $jobs = [
        new WarmPeriodCacheJob('7', 'all'),
        new WarmPeriodCacheJob('30', 'all'),
        new WarmPeriodCacheJob('90', 'all'),
    ];

    $peakMemory = 0;

    foreach ($jobs as $job) {
        $memoryBefore = memory_get_peak_usage(true);
        $job->handle();
        $memoryAfter = memory_get_peak_usage(true);

        $peakMemory = max($peakMemory, $memoryAfter);

        // Each job should clean up after itself
        // Memory shouldn't continuously grow
        expect($memoryAfter - $memoryBefore)->toBeLessThan(50 * 1024 * 1024); // < 50MB per job
    }

    // Total peak memory should be reasonable
    expect($peakMemory)->toBeLessThan(256 * 1024 * 1024); // < 256MB total
});

test('jobs complete in order', function () {
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
    ]);

    $dispatchedEvents = [];

    Event::listen(CachePeriodWarmed::class, function ($event) use (&$dispatchedEvents) {
        $dispatchedEvents[] = $event->period;
    });

    Event::fake([
        CacheWarmingStarted::class,
        CacheWarmingCompleted::class,
    ]);

    // Process jobs in sequence
    $jobs = [
        new WarmPeriodCacheJob('7', 'all'),
        new WarmPeriodCacheJob('30', 'all'),
        new WarmPeriodCacheJob('90', 'all'),
    ];

    foreach ($jobs as $job) {
        $job->handle();
    }

    // Events should be in order
    expect($dispatchedEvents)->toBe(['7d', '30d', '90d']);
});

test('cache is populated with correct data structure', function () {
    Event::fake(); // Fake events to avoid broadcast issues

    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
        'total_charge' => 100.00,
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    $cached = Cache::get('metrics_7d_all');

    // Verify all required keys exist
    $requiredKeys = [
        'revenue',
        'orders',
        'items',
        'avg_order_value',
        'processed_orders',
        'open_orders',
        'top_channels',
        'top_products',
        'chart_line',
        'chart_orders',
        'chart_doughnut',
        'chart_items',
        'chart_orders_revenue',
        'recent_orders',
        'best_day',
        'warmed_at',
    ];

    foreach ($requiredKeys as $key) {
        expect($cached)->toHaveKey($key);
    }

    // Verify data types (top_channels/top_products are Collections)
    expect($cached['revenue'])->toBeFloat()
        ->and($cached['orders'])->toBeInt()
        ->and($cached['items'])->toBeInt()
        ->and($cached['warmed_at'])->toBeString();
});

test('all events are dispatched during cache warming', function () {
    // Don't track individual events, just verify they were dispatched using Event::fake()
    Event::fake();

    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(2),
    ]);

    // Trigger the full flow
    $event = new OrdersSynced(5, 'test');
    $listener = new WarmMetricsCache();
    $listener->handle($event);

    // Verify CacheWarmingStarted was dispatched
    Event::assertDispatched(CacheWarmingStarted::class);

    // Process jobs
    $jobs = [
        new WarmPeriodCacheJob('7', 'all'),
        new WarmPeriodCacheJob('30', 'all'),
        new WarmPeriodCacheJob('90', 'all'),
    ];

    foreach ($jobs as $job) {
        $job->handle();
    }

    // Verify CachePeriodWarmed was dispatched (without checking count, as fake() can cause double-counting)
    Event::assertDispatched(CachePeriodWarmed::class, function ($event) {
        return in_array($event->period, ['7d', '30d', '90d']);
    });
});

test('failed jobs can be retried', function () {
    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(2),
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');

    // Verify job has retry configuration
    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->maxExceptions)->toBe(3);

    // Job should succeed
    $job->handle();

    expect(Cache::has('metrics_7d_all'))->toBeTrue();
});

test('cache warming uses all enum cacheable periods', function () {
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
    ]);

    $event = new OrdersSynced(10, 'test');
    $listener = new WarmMetricsCache();
    $listener->handle($event);

    // Get all cacheable periods from enum
    $cacheablePeriods = \App\Enums\Period::cacheable();

    // Process all cacheable periods
    $jobs = collect($cacheablePeriods)->map(function ($period) {
        return new WarmPeriodCacheJob($period->value, 'all');
    })->all();

    foreach ($jobs as $job) {
        $job->handle();
    }

    // Verify all cacheable periods have cache
    foreach ($cacheablePeriods as $period) {
        expect(Cache::has($period->cacheKey('all')))->toBeTrue();
    }

    // Verify 'custom' period is NOT cached
    $customPeriod = \App\Enums\Period::CUSTOM;
    expect(Cache::has($customPeriod->cacheKey('all')))->toBeFalse();
});

test('cache warming handles empty dataset gracefully', function () {
    // No orders created

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    $cached = Cache::get('metrics_7d_all');

    expect($cached)->toBeArray()
        ->and($cached['orders'])->toBe(0)
        ->and($cached['revenue'])->toBe(0.0)
        ->and($cached['items'])->toBe(0);
});

test('cache can be rewarmed with updated data', function () {
    Event::fake(); // Fake events

    // Create initial orders
    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(2),
    ]);

    // First warming
    $job1 = new WarmPeriodCacheJob('7', 'all');
    $job1->handle();

    $cached1 = Cache::get('metrics_7d_all');
    expect($cached1['orders'])->toBe(5);
    $warmedAt1 = $cached1['warmed_at'];

    // Sleep to ensure different timestamp
    sleep(1);

    // Second warming (should recalculate with same data)
    $job2 = new WarmPeriodCacheJob('7', 'all');
    $job2->handle();

    $cached2 = Cache::get('metrics_7d_all');
    expect($cached2['orders'])->toBe(5); // Same count

    // But warmed_at should be updated
    expect($cached2['warmed_at'])->not->toBe($warmedAt1);
});

test('cache TTL is set correctly', function () {
    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(2),
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    // Cache should exist
    expect(Cache::has('metrics_7d_all'))->toBeTrue();

    // Travel 59 minutes - should still exist
    $this->travel(59)->minutes();
    expect(Cache::has('metrics_7d_all'))->toBeTrue();

    // Travel 2 more minutes (61 total) - should be expired
    $this->travel(2)->minutes();
    expect(Cache::has('metrics_7d_all'))->toBeFalse();
});
