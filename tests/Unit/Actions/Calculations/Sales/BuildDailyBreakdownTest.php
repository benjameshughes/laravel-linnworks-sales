<?php

declare(strict_types=1);

use App\Actions\Calculations\Sales\BuildDailyBreakdown;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('BuildDailyBreakdown', function () {
    it('returns empty data structure when no orders provided', function () {
        $action = new BuildDailyBreakdown;
        $dateRange = collect([
            Carbon::parse('2025-01-13'),
            Carbon::parse('2025-01-14'),
            Carbon::parse('2025-01-15'),
        ]);

        $result = $action(collect([]), $dateRange);

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3)
            ->and($result[0])
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($result[0]['date'])
            ->toBe('Jan 13, 2025')
            ->and($result[0]['revenue'])
            ->toBe(0.0)
            ->and($result[0]['orders'])
            ->toBe(0)
            ->and($result[0]['items'])
            ->toBe(0)
            ->and($result[0]['avg_order_value'])
            ->toBe(0);
    });

    it('aggregates orders by date correctly', function () {
        $orders = collect([
            (object) [
                'received_at' => Carbon::parse('2025-01-13'),
                'total_charge' => 100.00,
                'items' => [
                    ['sku' => 'ABC123', 'quantity' => 2],
                    ['sku' => 'DEF456', 'quantity' => 1],
                ],
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-13'),
                'total_charge' => 50.00,
                'items' => [
                    ['sku' => 'GHI789', 'quantity' => 1],
                ],
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-14'),
                'total_charge' => 200.00,
                'items' => [
                    ['sku' => 'JKL012', 'quantity' => 5],
                ],
            ],
        ]);

        $dateRange = collect([
            Carbon::parse('2025-01-13'),
            Carbon::parse('2025-01-14'),
            Carbon::parse('2025-01-15'),
        ]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result)
            ->toHaveCount(3)
            ->and($result[0]['iso_date'])
            ->toBe('2025-01-13')
            ->and($result[0]['revenue'])
            ->toBe(150.00)
            ->and($result[0]['orders'])
            ->toBe(2)
            ->and($result[0]['items'])
            ->toBe(4)
            ->and($result[0]['avg_order_value'])
            ->toBe(75.00)
            ->and($result[1]['iso_date'])
            ->toBe('2025-01-14')
            ->and($result[1]['revenue'])
            ->toBe(200.00)
            ->and($result[1]['orders'])
            ->toBe(1)
            ->and($result[1]['items'])
            ->toBe(5)
            ->and($result[1]['avg_order_value'])
            ->toBe(200.00)
            ->and($result[2]['iso_date'])
            ->toBe('2025-01-15')
            ->and($result[2]['revenue'])
            ->toBe(0.0)
            ->and($result[2]['orders'])
            ->toBe(0)
            ->and($result[2]['items'])
            ->toBe(0);
    });

    it('skips orders without received_date', function () {
        $orders = collect([
            (object) [
                'received_at' => null,
                'total_charge' => 100.00,
                'items' => [['sku' => 'ABC123', 'quantity' => 1]],
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-13'),
                'total_charge' => 50.00,
                'items' => [['sku' => 'DEF456', 'quantity' => 1]],
            ],
        ]);

        $dateRange = collect([Carbon::parse('2025-01-13')]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['revenue'])->toBe(50.00)
            ->and($result[0]['orders'])->toBe(1);
    });

    it('formats dates correctly', function () {
        $orders = collect([]);
        $dateRange = collect([
            Carbon::parse('2025-01-13'),
            Carbon::parse('2025-02-28'),
        ]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['date'])->toBe('Jan 13, 2025')
            ->and($result[0]['iso_date'])->toBe('2025-01-13')
            ->and($result[0]['day'])->toBe('Mon')
            ->and($result[1]['date'])->toBe('Feb 28, 2025')
            ->and($result[1]['iso_date'])->toBe('2025-02-28')
            ->and($result[1]['day'])->toBe('Fri');
    });

    it('handles orders with no items', function () {
        $orders = collect([
            (object) [
                'received_at' => Carbon::parse('2025-01-13'),
                'total_charge' => 100.00,
                'items' => null,
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-13'),
                'total_charge' => 50.00,
                'items' => [],
            ],
        ]);

        $dateRange = collect([Carbon::parse('2025-01-13')]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['revenue'])->toBe(150.00)
            ->and($result[0]['orders'])->toBe(2)
            ->and($result[0]['items'])->toBe(0);
    });

    it('calculates average order value correctly with zero orders', function () {
        $orders = collect([]);
        $dateRange = collect([Carbon::parse('2025-01-13')]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['avg_order_value'])->toBe(0);
    });

    it('handles string received_date correctly', function () {
        $orders = collect([
            (object) [
                'received_at' => '2025-01-13 10:30:00',
                'total_charge' => 100.00,
                'items' => [['sku' => 'ABC123', 'quantity' => 1]],
            ],
        ]);

        $dateRange = collect([Carbon::parse('2025-01-13')]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['revenue'])->toBe(100.00)
            ->and($result[0]['orders'])->toBe(1);
    });

    it('ignores orders outside date range', function () {
        $orders = collect([
            (object) [
                'received_at' => Carbon::parse('2025-01-10'),
                'total_charge' => 100.00,
                'items' => [['sku' => 'ABC123', 'quantity' => 1]],
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-13'),
                'total_charge' => 50.00,
                'items' => [['sku' => 'DEF456', 'quantity' => 1]],
            ],
        ]);

        $dateRange = collect([Carbon::parse('2025-01-13')]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['revenue'])->toBe(50.00)
            ->and($result[0]['orders'])->toBe(1);
    });

    it('handles multiple orders on same day', function () {
        $orders = collect([
            (object) [
                'received_at' => Carbon::parse('2025-01-13 09:00:00'),
                'total_charge' => 100.00,
                'items' => [['sku' => 'ABC', 'quantity' => 1]],
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-13 14:00:00'),
                'total_charge' => 50.00,
                'items' => [['sku' => 'DEF', 'quantity' => 2]],
            ],
            (object) [
                'received_at' => Carbon::parse('2025-01-13 23:30:00'),
                'total_charge' => 75.00,
                'items' => [['sku' => 'GHI', 'quantity' => 1]],
            ],
        ]);

        $dateRange = collect([Carbon::parse('2025-01-13')]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result[0]['revenue'])->toBe(225.00)
            ->and($result[0]['orders'])->toBe(3)
            ->and($result[0]['items'])->toBe(4)
            ->and($result[0]['avg_order_value'])->toBe(75.00);
    });

    it('returns collection values not associative array', function () {
        $orders = collect([]);
        $dateRange = collect([
            Carbon::parse('2025-01-13'),
            Carbon::parse('2025-01-14'),
        ]);

        $action = new BuildDailyBreakdown;
        $result = $action($orders, $dateRange);

        expect($result->keys()->toArray())->toBe([0, 1]);
    });
});
