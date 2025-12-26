<?php

declare(strict_types=1);

use App\Enums\Period;
use App\Events\OrdersSynced;
use App\Jobs\WarmProductMetricsCacheJob;
use App\Listeners\WarmProductMetricsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

describe('WarmProductMetricsCache listener', function () {
    it('dispatches batch of cache warming jobs on OrdersSynced event', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 10, syncType: 'open_orders');

        $listener->handle($event);

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'warm-product-cache';
        });
    });

    it('creates jobs for all cacheable periods', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 5);

        $listener->handle($event);

        $cacheablePeriods = Period::cacheable();

        Bus::assertBatched(function ($batch) use ($cacheablePeriods) {
            return count($batch->jobs) === count($cacheablePeriods);
        });
    });

    it('creates WarmProductMetricsCacheJob for each period', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 15);

        $listener->handle($event);

        $cacheablePeriods = Period::cacheable();

        foreach ($cacheablePeriods as $period) {
            Bus::assertBatched(function ($batch) use ($period) {
                return collect($batch->jobs)->contains(function ($job) use ($period) {
                    return $job instanceof WarmProductMetricsCacheJob
                        && $job->period === $period->value;
                });
            });
        }
    });

    it('dispatches batch successfully', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 20);

        $listener->handle($event);

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'warm-product-cache' && count($batch->jobs) > 0;
        });
    });

    it('names the batch warm-product-cache', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 8);

        $listener->handle($event);

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'warm-product-cache';
        });
    });

    it('handles events with different sync types', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;

        $event1 = new OrdersSynced(ordersProcessed: 10, syncType: 'open_orders');
        $event2 = new OrdersSynced(ordersProcessed: 50, syncType: 'all_orders');
        $event3 = new OrdersSynced(ordersProcessed: 100, syncType: 'historical');

        $listener->handle($event1);
        $listener->handle($event2);
        $listener->handle($event3);

        Bus::assertBatchCount(3);
    });

    it('handles zero orders synced event', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 0);

        $listener->handle($event);

        Bus::assertBatched(function ($batch) {
            return $batch->name === 'warm-product-cache';
        });
    });

    it('creates correct number of jobs based on cacheable periods', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 25);

        $listener->handle($event);

        $expectedCount = count(Period::cacheable());

        Bus::assertBatched(function ($batch) use ($expectedCount) {
            return count($batch->jobs) === $expectedCount;
        });
    });

    it('excludes custom period from jobs', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 12);

        $listener->handle($event);

        Bus::assertBatched(function ($batch) {
            return ! collect($batch->jobs)->contains(function ($job) {
                return $job instanceof WarmProductMetricsCacheJob
                    && $job->period === Period::CUSTOM->value;
            });
        });
    });

    it('creates jobs with correct period values', function () {
        Bus::fake();

        $listener = new WarmProductMetricsCache;
        $event = new OrdersSynced(ordersProcessed: 30);

        $listener->handle($event);

        $cacheablePeriods = Period::cacheable();

        Bus::assertBatched(function ($batch) use ($cacheablePeriods) {
            $jobPeriods = collect($batch->jobs)
                ->map(fn ($job) => $job->period)
                ->sort()
                ->values()
                ->toArray();

            $expectedPeriods = collect($cacheablePeriods)
                ->map(fn ($period) => $period->value)
                ->sort()
                ->values()
                ->toArray();

            return $jobPeriods === $expectedPeriods;
        });
    });
});
