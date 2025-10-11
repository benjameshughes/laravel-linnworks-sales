<?php

use App\Events\CacheCleared;
use App\Events\CachePeriodWarmed;
use App\Events\CacheWarmingCompleted;
use App\Events\CacheWarmingStarted;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

test('CacheWarmingStarted broadcasts on cache-management channel', function () {
    $event = new CacheWarmingStarted(['7d', '30d', '90d']);

    $channels = $event->broadcastOn();

    expect($channels)->toBeInstanceOf(Channel::class)
        ->and($channels->name)->toBe('cache-management');
});

test('CacheWarmingStarted broadcasts with correct data', function () {
    $periods = ['7d', '30d', '90d'];
    $event = new CacheWarmingStarted($periods);

    $data = $event->broadcastWith();

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('periods')
        ->and($data['periods'])->toBe($periods);
});

test('CacheWarmingStarted implements ShouldBroadcastNow', function () {
    $event = new CacheWarmingStarted(['7d']);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

test('CachePeriodWarmed broadcasts on cache-management channel', function () {
    $event = new CachePeriodWarmed('7d', 100, 5000.00, 250);

    $channels = $event->broadcastOn();

    expect($channels)->toBeInstanceOf(Channel::class)
        ->and($channels->name)->toBe('cache-management');
});

test('CachePeriodWarmed broadcasts with correct data', function () {
    $event = new CachePeriodWarmed('7d', 100, 5000.00, 250);

    $data = $event->broadcastWith();

    expect($data)->toBeArray()
        ->and($data['period'])->toBe('7d')
        ->and($data['orders'])->toBe(100)
        ->and($data['revenue'])->toBe(5000.00)
        ->and($data['items'])->toBe(250)
        ->and($data)->toHaveKey('warmed_at');
});

test('CachePeriodWarmed implements ShouldBroadcastNow', function () {
    $event = new CachePeriodWarmed('7d', 100, 5000.00, 250);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

test('CachePeriodWarmed includes timestamp in broadcast', function () {
    $event = new CachePeriodWarmed('7d', 100, 5000.00, 250);

    $data = $event->broadcastWith();

    expect($data['warmed_at'])->toBeString()
        ->and($data['warmed_at'])->toContain('T'); // ISO 8601 format
});

test('CacheWarmingCompleted broadcasts on cache-management channel', function () {
    $event = new CacheWarmingCompleted(3);

    $channels = $event->broadcastOn();

    expect($channels)->toBeInstanceOf(Channel::class)
        ->and($channels->name)->toBe('cache-management');
});

test('CacheWarmingCompleted broadcasts with correct data', function () {
    $event = new CacheWarmingCompleted(3);

    $data = $event->broadcastWith();

    expect($data)->toBeArray()
        ->and($data['periods_warmed'])->toBe(3)
        ->and($data['success'])->toBeTrue()
        ->and($data)->toHaveKey('completed_at');
});

test('CacheWarmingCompleted implements ShouldBroadcastNow', function () {
    $event = new CacheWarmingCompleted(3);

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

test('CacheCleared broadcasts on cache-management channel', function () {
    $event = new CacheCleared();

    $channels = $event->broadcastOn();

    expect($channels)->toBeInstanceOf(Channel::class)
        ->and($channels->name)->toBe('cache-management');
});

test('CacheCleared broadcasts with correct data', function () {
    $event = new CacheCleared();

    $data = $event->broadcastWith();

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('cleared_at');
});

test('CacheCleared implements ShouldBroadcastNow', function () {
    $event = new CacheCleared();

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class);
});

test('all cache events use the same channel', function () {
    $started = new CacheWarmingStarted(['7d']);
    $warmed = new CachePeriodWarmed('7d', 100, 5000.00, 250);
    $completed = new CacheWarmingCompleted(3);
    $cleared = new CacheCleared();

    expect($started->broadcastOn()->name)->toBe('cache-management')
        ->and($warmed->broadcastOn()->name)->toBe('cache-management')
        ->and($completed->broadcastOn()->name)->toBe('cache-management')
        ->and($cleared->broadcastOn()->name)->toBe('cache-management');
});

test('CachePeriodWarmed handles different data types', function () {
    // Test with integer revenue (PHP will cast to float)
    $event1 = new CachePeriodWarmed('7d', 100, 5000, 250);
    expect($event1->revenue)->toBe(5000.0);

    // Test with float revenue
    $event2 = new CachePeriodWarmed('30d', 500, 15000.50, 1200);
    expect($event2->revenue)->toBe(15000.50);

    // Test with zero values
    $event3 = new CachePeriodWarmed('90d', 0, 0.0, 0);
    expect($event3->orders)->toBe(0)
        ->and($event3->revenue)->toBe(0.0)
        ->and($event3->items)->toBe(0);
});
