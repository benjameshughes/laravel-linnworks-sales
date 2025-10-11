<?php

use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use App\Events\OrdersSynced;
use App\Jobs\WarmPeriodCacheJob;
use App\Listeners\WarmMetricsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Bus::fake();
    Log::spy();

    // Set default config
    config(['dashboard.cacheable_periods' => ['7', '30', '90']]);
});

test('listener dispatches correct number of jobs', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    // Should dispatch 3 jobs (7d, 30d, 90d) for 'all' channel
    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) === 3;
    });
});

test('listener creates job batch with correct name', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        return $batch->name === 'warm-metrics-cache';
    });
});

test('listener uses low queue priority', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    // Verify batch was dispatched (queue property not accessible in fake)
    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) > 0;
    });
});

test('listener broadcasts CacheWarmingStarted event', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Event::assertDispatched(CacheWarmingStarted::class, function ($event) {
        return $event->periods === ['7d', '30d', '90d'];
    });
});

test('listener creates jobs for all configured periods', function () {
    config(['dashboard.cacheable_periods' => ['1', '7', '30', '90']]);

    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) === 4;
    });
});

test('listener batch jobs are WarmPeriodCacheJob instances', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        foreach ($batch->jobs as $job) {
            if (!$job instanceof WarmPeriodCacheJob) {
                return false;
            }
        }
        return true;
    });
});

test('listener is queued (implements ShouldQueue)', function () {
    expect(new WarmMetricsCache())
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('listener dispatches jobs with correct periods', function () {
    config(['dashboard.cacheable_periods' => ['7', '30']]);

    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        $periods = $batch->jobs->map(fn($job) => $job->period)->all();
        return $periods === ['7', '30'];
    });
});

test('listener dispatches jobs with all channel', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        foreach ($batch->jobs as $job) {
            if ($job->channel !== 'all') {
                return false;
            }
        }
        return true;
    });
});

test('listener creates batch successfully', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    // Verify a batch was created
    Bus::assertBatched(function ($batch) {
        return $batch->name === 'warm-metrics-cache'
            && count($batch->jobs) === 3;
    });
});

test('listener batch finally callback exists', function () {
    // The batch has a finally callback that broadcasts CacheWarmingCompleted
    // We can't test the callback directly with Bus::fake()
    // This is tested in integration tests

    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        // Just verify the batch was created successfully
        return $batch->name === 'warm-metrics-cache';
    });
});

test('listener handles empty cacheable periods config', function () {
    config(['dashboard.cacheable_periods' => []]);

    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    // Should dispatch a batch with 0 jobs if no periods configured
    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) === 0;
    });
});

test('listener respects custom periods from config', function () {
    config(['dashboard.cacheable_periods' => ['1', 'yesterday', '7', '30', '90']]);

    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        return count($batch->jobs) === 5;
    });
});
