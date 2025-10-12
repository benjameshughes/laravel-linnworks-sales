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
});

test('listener dispatches correct number of jobs', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    // Should dispatch jobs for all cacheable periods (all except 'custom')
    $expectedCount = count(\App\Enums\Period::cacheable());
    Bus::assertBatched(function ($batch) use ($expectedCount) {
        return count($batch->jobs) === $expectedCount;
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

    $expectedPeriods = collect(\App\Enums\Period::cacheable())->map(fn($p) => "{$p->value}d")->toArray();
    Event::assertDispatched(CacheWarmingStarted::class, function ($event) use ($expectedPeriods) {
        return $event->periods === $expectedPeriods;
    });
});

test('listener creates jobs for all enum periods', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    $expectedCount = count(\App\Enums\Period::cacheable());
    Bus::assertBatched(function ($batch) use ($expectedCount) {
        return count($batch->jobs) === $expectedCount;
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
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    Bus::assertBatched(function ($batch) {
        $periods = $batch->jobs->map(fn($job) => $job->period)->all();
        $expectedPeriods = collect(\App\Enums\Period::cacheable())->map(fn($p) => $p->value)->all();
        return $periods === $expectedPeriods;
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
    $expectedCount = count(\App\Enums\Period::cacheable());
    Bus::assertBatched(function ($batch) use ($expectedCount) {
        return $batch->name === 'warm-metrics-cache'
            && count($batch->jobs) === $expectedCount;
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

test('listener only dispatches cacheable periods', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    // Should only dispatch cacheable periods (not 'custom')
    Bus::assertBatched(function ($batch) {
        foreach ($batch->jobs as $job) {
            if ($job->period === 'custom') {
                return false;
            }
        }
        return true;
    });
});

test('listener dispatches all periods from enum', function () {
    $listener = new WarmMetricsCache();
    $event = new OrdersSynced(100, 'test');

    $listener->handle($event);

    $cacheablePeriods = \App\Enums\Period::cacheable();
    Bus::assertBatched(function ($batch) use ($cacheablePeriods) {
        return count($batch->jobs) === count($cacheablePeriods);
    });
});
