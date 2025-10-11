<?php

use App\Events\CacheCleared;
use App\Events\CachePeriodWarmed;
use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use App\Events\OrdersSynced;
use App\Livewire\Settings\CacheManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
    Event::fake();
    Cache::flush();

    // Create log file for testing
    $logPath = storage_path('logs/laravel.log');
    if (!File::exists(dirname($logPath))) {
        File::makeDirectory(dirname($logPath), 0755, true);
    }
    File::put($logPath, '');
});

afterEach(function () {
    // Clean up test log file
    $logPath = storage_path('logs/laravel.log');
    if (File::exists($logPath)) {
        File::delete($logPath);
    }
});

test('admin user can mount component', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    expect($component->isWarming)->toBeFalse()
        ->and($component->isClearing)->toBeFalse()
        ->and($component->currentlyWarmingPeriod)->toBeNull();
});

test('mount detects active warming batch', function () {
    $this->actingAs($this->admin);

    // Create an unfinished batch
    DB::table('job_batches')->insert([
        'id' => 'test-batch-id',
        'name' => 'warm-metrics-cache',
        'total_jobs' => 3,
        'pending_jobs' => 2,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => '[]',
        'created_at' => time(),
        'cancelled_at' => null,
        'finished_at' => null,
    ]);

    $component = new CacheManagement();
    $component->mount();

    // Should detect warming in progress
    expect($component->isWarming)->toBeTrue();
});

test('mount detects currently warming period', function () {
    $this->actingAs($this->admin);

    // Create an unfinished batch
    DB::table('job_batches')->insert([
        'id' => 'test-batch-id',
        'name' => 'warm-metrics-cache',
        'total_jobs' => 3,
        'pending_jobs' => 2,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => '[]',
        'created_at' => time(),
        'cancelled_at' => null,
        'finished_at' => null,
    ]);

    // 7d is already cached, so mount should detect 30d as currently warming
    Cache::put('metrics_7d_all', ['data'], 3600);

    $component = new CacheManagement();
    $component->mount();

    expect($component->isWarming)->toBeTrue()
        ->and($component->currentlyWarmingPeriod)->toBe('30d');
});

test('non-admin user cannot mount component', function () {
    $this->actingAs($this->user);

    $component = new CacheManagement();

    expect(fn() => $component->mount())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('cacheStatus returns correct cache state for cold cache', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    $status = $component->cacheStatus();

    expect($status)->toBeArray()
        ->and($status['7d'])->toBeArray()
        ->and($status['7d']['exists'])->toBeFalse()
        ->and($status['30d'])->toBeArray()
        ->and($status['30d']['exists'])->toBeFalse()
        ->and($status['90d'])->toBeArray()
        ->and($status['90d']['exists'])->toBeFalse();
});

test('cacheStatus returns correct cache state for warm cache', function () {
    $this->actingAs($this->admin);

    // Warm the cache
    Cache::put('metrics_7d_all', [
        'revenue' => 5000.00,
        'orders' => 100,
        'items' => 250,
        'warmed_at' => now()->toISOString(),
    ], 3600);

    $component = new CacheManagement();
    $component->mount();

    $status = $component->cacheStatus();

    expect($status['7d']['exists'])->toBeTrue()
        ->and($status['7d']['revenue'])->toBe(5000.00)
        ->and($status['7d']['orders'])->toBe(100)
        ->and($status['7d']['items'])->toBe(250);
});

test('activeBatch returns null when no batch exists', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    expect($component->activeBatch())->toBeNull();
});

test('activeBatch returns correct batch data', function () {
    $this->actingAs($this->admin);

    // Create a test batch in the database
    DB::table('job_batches')->insert([
        'id' => 'test-batch-id',
        'name' => 'warm-metrics-cache',
        'total_jobs' => 5,
        'pending_jobs' => 2,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => '[]',
        'created_at' => time(),
        'cancelled_at' => null,
        'finished_at' => null,
    ]);

    $component = new CacheManagement();
    $component->mount();

    $batch = $component->activeBatch();

    expect($batch)->toBeArray()
        ->and($batch['id'])->toBe('test-batch-id')
        ->and($batch['total_jobs'])->toBe(5)
        ->and($batch['pending_jobs'])->toBe(2)
        ->and($batch['processed_jobs'])->toBe(3)
        ->and($batch['failed_jobs'])->toBe(0)
        ->and($batch['progress'])->toBe(60.0)
        ->and($batch['finished'])->toBeFalse();
});

test('activeBatch calculates progress correctly', function () {
    $this->actingAs($this->admin);

    DB::table('job_batches')->insert([
        'id' => 'test-batch-id',
        'name' => 'warm-metrics-cache',
        'total_jobs' => 10,
        'pending_jobs' => 3,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => '[]',
        'created_at' => time(),
        'cancelled_at' => null,
        'finished_at' => null,
    ]);

    $component = new CacheManagement();
    $component->mount();

    $batch = $component->activeBatch();

    expect($batch['progress'])->toBe(70.0); // 7/10 * 100
});

test('activeBatch shows finished status correctly', function () {
    $this->actingAs($this->admin);

    DB::table('job_batches')->insert([
        'id' => 'test-batch-id',
        'name' => 'warm-metrics-cache',
        'total_jobs' => 5,
        'pending_jobs' => 0,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => '[]',
        'created_at' => time(),
        'cancelled_at' => null,
        'finished_at' => time(),
    ]);

    $component = new CacheManagement();
    $component->mount();

    $batch = $component->activeBatch();

    expect($batch['finished'])->toBeTrue()
        ->and($batch['progress'])->toBe(100.0);
});

test('recentCacheWarming returns empty array when no logs', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    expect($component->recentCacheWarming())->toBe([]);
});

test('recentCacheWarming parses logs correctly', function () {
    $this->actingAs($this->admin);

    // Write test log entries
    $logContent = [
        '[2025-10-11 10:00:00] local.INFO: Cache warmed successfully {"cache_key":"metrics_7d_all","orders_count":100,"duration_seconds":0.5,"memory_used_mb":10.5,"peak_memory_mb":50.2}',
        '[2025-10-11 10:00:05] local.INFO: Cache warmed successfully {"cache_key":"metrics_30d_all","orders_count":500,"duration_seconds":4.2,"memory_used_mb":42.0,"peak_memory_mb":98.5}',
    ];

    File::put(storage_path('logs/laravel.log'), implode("\n", $logContent));

    $component = new CacheManagement();
    $component->mount();

    $logs = $component->recentCacheWarming();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]['cache_key'])->toBe('metrics_30d_all')
        ->and($logs[0]['orders_count'])->toBe(500)
        ->and($logs[0]['memory_used_mb'])->toBe(42.0)
        ->and($logs[0]['peak_memory_mb'])->toBe(98.5)
        ->and($logs[1]['cache_key'])->toBe('metrics_7d_all');
});

test('recentCacheWarming returns last 5 entries only', function () {
    $this->actingAs($this->admin);

    // Write 10 test log entries
    $logContent = [];
    for ($i = 0; $i < 10; $i++) {
        $logContent[] = "[2025-10-11 10:00:{$i}] local.INFO: Cache warmed successfully {\"cache_key\":\"metrics_7d_all\",\"orders_count\":100,\"duration_seconds\":0.5,\"memory_used_mb\":10.0,\"peak_memory_mb\":50.0}";
    }

    File::put(storage_path('logs/laravel.log'), implode("\n", $logContent));

    $component = new CacheManagement();
    $component->mount();

    $logs = $component->recentCacheWarming();

    expect($logs)->toHaveCount(5);
});

test('queuedJobs returns correct count', function () {
    $this->actingAs($this->admin);

    // Add some jobs to the queue
    DB::table('jobs')->insert([
        ['queue' => 'low', 'payload' => 'test1', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
        ['queue' => 'low', 'payload' => 'test2', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
        ['queue' => 'default', 'payload' => 'test3', 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
    ]);

    $component = new CacheManagement();
    $component->mount();

    expect($component->queuedJobs())->toBe(2); // Only 'low' queue
});

test('warmCache dispatches OrdersSynced event', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();
    $component->warmCache();

    Event::assertDispatched(OrdersSynced::class, function ($event) {
        return $event->ordersProcessed === 0
            && $event->syncType === 'manual_warm';
    });
});

test('warmCache sets isWarming to true', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    expect($component->isWarming)->toBeFalse();

    $component->warmCache();

    expect($component->isWarming)->toBeTrue()
        ->and($component->currentlyWarmingPeriod)->toBeNull();
});

test('clearCache removes all cached periods', function () {
    $this->actingAs($this->admin);

    // Set up cache
    Cache::put('metrics_7d_all', ['data'], 3600);
    Cache::put('metrics_30d_all', ['data'], 3600);
    Cache::put('metrics_90d_all', ['data'], 3600);

    expect(Cache::has('metrics_7d_all'))->toBeTrue();

    $component = new CacheManagement();
    $component->mount();
    $component->clearCache();

    expect(Cache::has('metrics_7d_all'))->toBeFalse()
        ->and(Cache::has('metrics_30d_all'))->toBeFalse()
        ->and(Cache::has('metrics_90d_all'))->toBeFalse();
});

test('clearCache broadcasts CacheCleared event', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();
    $component->clearCache();

    Event::assertDispatched(CacheCleared::class);
});

test('clearCache sets isClearing to true', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    expect($component->isClearing)->toBeFalse();

    $component->clearCache();

    expect($component->isClearing)->toBeTrue();
});

test('handleWarmingStarted updates component state', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    $component->handleWarmingStarted(['periods' => ['7d', '30d', '90d']]);

    expect($component->isWarming)->toBeTrue()
        ->and($component->currentlyWarmingPeriod)->toBeNull();
});

test('handlePeriodWarmingStarted sets currentlyWarmingPeriod', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    $component->handlePeriodWarmingStarted(['period' => '7d']);

    expect($component->currentlyWarmingPeriod)->toBe('7d');
});

test('handlePeriodWarmed clears currently warming period', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    $component->currentlyWarmingPeriod = '7d';
    $component->handlePeriodWarmed(['period' => '7d']);

    expect($component->currentlyWarmingPeriod)->toBeNull();
});

test('handlePeriodWarmed resets computed properties', function () {
    $this->actingAs($this->admin);

    Cache::put('metrics_7d_all', ['revenue' => 5000], 3600);

    $component = new CacheManagement();
    $component->mount();

    // Access computed property to cache it
    $status1 = $component->cacheStatus();
    expect($status1['7d']['exists'])->toBeTrue();

    // Update cache
    Cache::forget('metrics_7d_all');

    // Call handlePeriodWarmed which should reset the computed property
    $component->handlePeriodWarmed(['period' => '7d']);

    // Access again - should be fresh
    $status2 = $component->cacheStatus();
    expect($status2['7d']['exists'])->toBeFalse();
});

test('handleWarmingCompleted resets component state', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    $component->isWarming = true;
    $component->currentlyWarmingPeriod = '7d';

    $component->handleWarmingCompleted(['periods_count' => 3]);

    expect($component->isWarming)->toBeFalse()
        ->and($component->currentlyWarmingPeriod)->toBeNull();
});

test('handleCacheCleared sets isClearing to false', function () {
    $this->actingAs($this->admin);

    $component = new CacheManagement();
    $component->mount();

    $component->isClearing = true;

    $component->handleCacheCleared(['cleared_at' => now()->toISOString()]);

    expect($component->isClearing)->toBeFalse();
});

test('component renders successfully', function () {
    $this->actingAs($this->admin);

    $component = Livewire\Livewire::test(CacheManagement::class);

    $component->assertStatus(200);
});
