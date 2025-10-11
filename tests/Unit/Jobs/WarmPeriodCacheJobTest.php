<?php

use App\Events\CachePeriodWarmed;
use App\Jobs\WarmPeriodCacheJob;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Log::spy();
    Cache::flush();
});

test('job executes successfully with valid period', function () {
    // Create some test orders
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(3),
        'total_charge' => 100.00,
        'status' => 'processed',
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    expect(Cache::has('metrics_7d_all'))->toBeTrue();
});

test('job calculates correct metrics', function () {
    // Create test orders with known values
    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(2),
        'total_charge' => 100.00,
        'status' => 'processed',
    ]);

    Order::factory()->count(3)->create([
        'received_date' => now()->subDays(3),
        'total_charge' => 50.00,
        'status' => 'processed',
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    $cached = Cache::get('metrics_7d_all');

    expect($cached)->toBeArray()
        ->and($cached['orders'])->toBe(8)
        ->and($cached['revenue'])->toBe(650.0) // 5*100 + 3*50
        ->and($cached)->toHaveKey('warmed_at')
        ->and($cached)->toHaveKey('items')
        ->and($cached)->toHaveKey('avg_order_value');
});

test('job stores data in cache with correct key', function () {
    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(15),
    ]);

    $job = new WarmPeriodCacheJob('30', 'all');
    $job->handle();

    expect(Cache::has('metrics_30d_all'))->toBeTrue()
        ->and(Cache::has('metrics_7d_all'))->toBeFalse();
});

test('job logs memory usage statistics', function () {
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    Log::shouldHaveReceived('info')
        ->with('Cache warmed successfully', \Mockery::on(function ($context) {
            return isset($context['cache_key'])
                && isset($context['orders_count'])
                && isset($context['memory_used_mb'])
                && isset($context['peak_memory_mb'])
                && isset($context['duration_seconds']);
        }));
});

test('job broadcasts CachePeriodWarmed event', function () {
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
        'total_charge' => 100.00,
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    Event::assertDispatched(CachePeriodWarmed::class, function ($event) {
        return $event->period === '7d'
            && $event->orders === 10
            && $event->revenue > 0;
    });
});

test('job has batch cancellation check', function () {
    // Verify the job has the Batchable trait which enables batch() method
    $job = new WarmPeriodCacheJob('7', 'all');

    expect(class_uses($job))
        ->toContain(\Illuminate\Bus\Batchable::class);

    // The actual batch cancellation is tested in integration tests
    // where we can properly set up a real batch
});

test('job has retry and timeout configuration', function () {
    $job = new WarmPeriodCacheJob('7', 'all');

    // Verify retry configuration exists
    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->maxExceptions)->toBe(3);

    // Job will log errors and re-throw exceptions for retry
    // This is tested in integration tests with actual failures
});

test('job caches data for configured TTL', function () {
    Order::factory()->count(5)->create([
        'received_date' => now()->subDays(2),
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    // Cache should exist
    expect(Cache::has('metrics_7d_all'))->toBeTrue();

    // Advance time by 59 minutes - cache should still exist
    $this->travel(59)->minutes();
    expect(Cache::has('metrics_7d_all'))->toBeTrue();

    // Advance time by 2 more minutes (total 61 minutes) - cache should be expired
    $this->travel(2)->minutes();
    expect(Cache::has('metrics_7d_all'))->toBeFalse();
});

test('job includes all required metrics in cached data', function () {
    Order::factory()->count(10)->create([
        'received_date' => now()->subDays(2),
    ]);

    $job = new WarmPeriodCacheJob('7', 'all');
    $job->handle();

    $cached = Cache::get('metrics_7d_all');

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
});

test('job processes different periods correctly', function () {
    // Create orders across different time ranges
    Order::factory()->count(3)->create(['received_date' => now()->subDays(2)]); // In 7d
    Order::factory()->count(5)->create(['received_date' => now()->subDays(15)]); // In 30d
    Order::factory()->count(7)->create(['received_date' => now()->subDays(45)]); // In 90d

    // Test 7d period
    $job7d = new WarmPeriodCacheJob('7', 'all');
    $job7d->handle();
    $cached7d = Cache::get('metrics_7d_all');
    expect($cached7d['orders'])->toBe(3);

    // Test 30d period
    $job30d = new WarmPeriodCacheJob('30', 'all');
    $job30d->handle();
    $cached30d = Cache::get('metrics_30d_all');
    expect($cached30d['orders'])->toBe(8); // 3 + 5

    // Test 90d period
    $job90d = new WarmPeriodCacheJob('90', 'all');
    $job90d->handle();
    $cached90d = Cache::get('metrics_90d_all');
    expect($cached90d['orders'])->toBe(15); // 3 + 5 + 7
});
