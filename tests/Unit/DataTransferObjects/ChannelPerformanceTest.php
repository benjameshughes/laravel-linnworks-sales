<?php

use App\Enums\GrowthDirection;
use App\DataTransferObjects\ChannelPerformance;

function makeChannel(array $overrides = []): ChannelPerformance
{
    return new ChannelPerformance(...array_merge([
        'name' => 'AMAZON',
        'source' => 'AMAZON',
        'subsource' => null,
        'currentRevenue' => 200.0,
        'baselineRevenue' => 100.0,
        'currentOrders' => 4,
        'baselineOrders' => 2,
        'currentItems' => 8,
        'baselineItems' => 4,
        'revenueShare' => 50.0,
    ], $overrides));
}

it('calculates revenue delta and growth', function () {
    $channel = makeChannel();

    expect($channel->revenueDelta())->toBe(100.0)
        ->and($channel->revenueGrowth())->toBe(100.0);
});

it('calculates a negative growth when the channel declines', function () {
    $channel = makeChannel(['currentRevenue' => 50.0, 'baselineRevenue' => 200.0]);

    expect($channel->revenueDelta())->toBe(-150.0)
        ->and($channel->revenueGrowth())->toBe(-75.0);
});

it('returns null growth when there is no baseline to measure against', function () {
    $channel = makeChannel(['baselineRevenue' => 0.0, 'baselineOrders' => 0]);

    expect($channel->revenueGrowth())->toBeNull()
        ->and($channel->ordersGrowth())->toBeNull()
        ->and($channel->aovGrowth())->toBeNull();
});

it('flags a channel with no baseline orders as new', function () {
    $channel = makeChannel(['baselineOrders' => 0, 'baselineRevenue' => 0.0]);

    expect($channel->isNew())->toBeTrue()
        ->and($channel->isLost())->toBeFalse();
});

it('flags a channel that stopped selling as lost', function () {
    $channel = makeChannel(['currentOrders' => 0, 'currentRevenue' => 0.0]);

    expect($channel->isLost())->toBeTrue()
        ->and($channel->isNew())->toBeFalse()
        ->and($channel->isImproving())->toBeFalse();
});

it('calculates average order value for both periods', function () {
    $channel = makeChannel();

    expect($channel->currentAov())->toBe(50.0)
        ->and($channel->baselineAov())->toBe(50.0)
        ->and($channel->aovGrowth())->toBe(0.0);
});

it('avoids division by zero when a period has no orders', function () {
    $channel = makeChannel(['currentOrders' => 0, 'baselineOrders' => 0]);

    expect($channel->currentAov())->toBe(0.0)
        ->and($channel->baselineAov())->toBe(0.0);
});

it('exposes every comparison figure through toArray', function () {
    $channel = makeChannel();

    expect($channel->toArray())
        ->toHaveKeys([
            'name', 'source', 'subsource',
            'current_revenue', 'baseline_revenue', 'revenue_delta', 'revenue_growth',
            'current_orders', 'baseline_orders', 'orders_delta', 'orders_growth',
            'current_items', 'baseline_items', 'items_delta',
            'current_aov', 'baseline_aov', 'aov_growth', 'revenue_share',
        ])
        ->and($channel->toArray()['items_delta'])->toBe(4);
});

it('colours a flat channel neutrally rather than as a loss', function () {
    $channel = makeChannel(['currentRevenue' => 100.0, 'baselineRevenue' => 100.0]);

    expect($channel->revenueDeltaDirection())->toBe(GrowthDirection::Flat)
        ->and($channel->formattedRevenueDelta())->toBe('£0.00');
});

it('puts the sign outside the currency symbol', function () {
    expect(makeChannel(['currentRevenue' => 250.0, 'baselineRevenue' => 100.0])->formattedRevenueDelta())
        ->toBe('+£150.00')
        ->and(makeChannel(['currentRevenue' => 80.0, 'baselineRevenue' => 100.0])->formattedRevenueDelta())
        ->toBe('-£20.00');
});

it('directs the delta tone by movement', function () {
    expect(makeChannel(['currentRevenue' => 250.0, 'baselineRevenue' => 100.0])->revenueDeltaDirection())
        ->toBe(GrowthDirection::Up)
        ->and(makeChannel(['currentRevenue' => 10.0, 'baselineRevenue' => 100.0])->revenueDeltaDirection())
        ->toBe(GrowthDirection::Down);
});
