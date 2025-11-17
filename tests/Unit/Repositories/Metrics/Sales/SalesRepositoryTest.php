<?php

declare(strict_types=1);

use App\Models\Order;
use App\Repositories\Metrics\Sales\SalesRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('SalesRepository', function () {
    it('gets all orders', function () {
        Order::factory()->count(5)->create();

        $repository = new SalesRepository;
        $orders = $repository->getAllOrders();

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(5);
    });

    it('gets recent orders with default limit', function () {
        Order::factory()->count(100)->create([
            'created_at' => now()->subDays(2),
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getRecentOrders();

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(50);
    });

    it('gets recent orders with custom limit', function () {
        Order::factory()->count(30)->create([
            'created_at' => now()->subDays(2),
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getRecentOrders(10);

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(10);
    });

    it('orders recent orders by most recent first', function () {
        $oldOrder = Order::factory()->create([
            'created_at' => now()->subDays(5),
            'order_number' => 'OLD',
        ]);

        $newOrder = Order::factory()->create([
            'created_at' => now()->subDay(),
            'order_number' => 'NEW',
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getRecentOrders(10);

        expect($orders->first()->order_number)->toBe('NEW')
            ->and($orders->last()->order_number)->toBe('OLD');
    });

    it('gets all open orders', function () {
        Order::factory()->count(3)->create([
            'is_processed' => false,
        ]);

        Order::factory()->count(2)->create([
            'is_processed' => true,
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getAllOpenOrders();

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3);

        $orders->each(function ($order) {
            expect($order->is_processed)->toBeFalse();
        });
    });

    it('gets all processed orders', function () {
        Order::factory()->count(3)->create([
            'is_processed' => true,
        ]);

        Order::factory()->count(2)->create([
            'is_processed' => false,
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getAllProcessedOrders();

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3);

        $orders->each(function ($order) {
            expect($order->is_processed)->toBeTrue();
        });
    });

    it('gets orders between dates', function () {
        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-05 10:00:00'),
        ]);

        Order::factory()->count(3)->create([
            'created_at' => Carbon::parse('2025-01-10 10:00:00'),
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-20 10:00:00'),
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getOrdersBetweenDates(
            Carbon::parse('2025-01-08'),
            Carbon::parse('2025-01-15')
        );

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3);
    });

    it('includes orders on boundary dates', function () {
        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-10 00:00:00'),
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-15 23:59:59'),
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-09 23:59:59'),
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getOrdersBetweenDates(
            Carbon::parse('2025-01-10 00:00:00'),
            Carbon::parse('2025-01-15 23:59:59')
        );

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(2);
    });

    it('gets orders for period', function () {
        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-05 10:00:00'),
        ]);

        Order::factory()->count(3)->create([
            'created_at' => Carbon::parse('2025-01-10 10:00:00'),
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-20 10:00:00'),
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getOrdersForPeriod(
            Carbon::parse('2025-01-08'),
            Carbon::parse('2025-01-15')
        );

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3);
    });

    it('returns empty collection when no orders match date range', function () {
        Order::factory()->count(5)->create([
            'created_at' => Carbon::parse('2025-01-01'),
        ]);

        $repository = new SalesRepository;
        $orders = $repository->getOrdersBetweenDates(
            Carbon::parse('2025-02-01'),
            Carbon::parse('2025-02-28')
        );

        expect($orders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(0);
    });

    it('returns empty collection when no orders exist', function () {
        $repository = new SalesRepository;

        expect($repository->getAllOrders())->toHaveCount(0)
            ->and($repository->getRecentOrders())->toHaveCount(0)
            ->and($repository->getAllOpenOrders())->toHaveCount(0)
            ->and($repository->getAllProcessedOrders())->toHaveCount(0);
    });
});
