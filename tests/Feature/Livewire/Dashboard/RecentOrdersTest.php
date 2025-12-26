<?php

declare(strict_types=1);

use App\Livewire\Dashboard\RecentOrders;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('RecentOrders Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(RecentOrders::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.recent-orders');
    });

    it('initializes with default values', function () {
        Livewire::test(RecentOrders::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('status', 'all');
    });

    it('responds to filters-updated event', function () {
        Livewire::test(RecentOrders::class)
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('computes recent orders correctly', function () {
        Order::factory()->count(20)->create([
            'received_at' => now()->subHour(),
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(30)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $recentOrders = $component->get('recentOrders');

        expect($recentOrders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(15);
    });

    it('limits recent orders to 15', function () {
        Order::factory()->count(50)->create([
            'received_at' => now()->subHour(),
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(30)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $recentOrders = $component->get('recentOrders');

        expect($recentOrders)->toHaveCount(15);
    });

    it('returns empty collection when no orders exist', function () {
        $component = Livewire::test(RecentOrders::class);

        $recentOrders = $component->get('recentOrders');

        expect($recentOrders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(0);
    });

    it('returns zero total orders for custom date ranges (not cached)', function () {
        // Custom periods cannot be cached, so totalOrders returns 0
        // This is by design to prevent OOM issues with uncached queries
        Order::factory()->count(10)->create([
            'received_at' => now()->subDays(3),
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(30)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $totalOrders = $component->get('totalOrders');

        expect($totalOrders)->toBe(0); // Custom periods return 0 by design
    });

    it('recent orders are ordered by most recent first', function () {
        $oldOrder = Order::factory()->create([
            'received_at' => now()->subDays(5),
            'number' => 'OLD123',
        ]);

        $newOrder = Order::factory()->create([
            'received_at' => now()->subDay(),
            'number' => 'NEW456',
        ]);

        // For custom periods, recentOrders fetches from repository (not cache)
        $component = Livewire::test(RecentOrders::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(30)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $recentOrders = $component->get('recentOrders');

        expect($recentOrders->first()->number)->toBe('NEW456')
            ->and($recentOrders->last()->number)->toBe('OLD123');
    });
});
