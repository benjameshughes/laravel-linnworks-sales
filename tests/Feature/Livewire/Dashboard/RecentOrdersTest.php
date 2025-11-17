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
            'created_at' => now()->subHour(),
        ]);

        $component = Livewire::test(RecentOrders::class);

        $recentOrders = $component->get('recentOrders');

        expect($recentOrders)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(15);
    });

    it('limits recent orders to 15', function () {
        Order::factory()->count(50)->create([
            'created_at' => now()->subHour(),
        ]);

        $component = Livewire::test(RecentOrders::class);

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

    it('computes total orders correctly', function () {
        Order::factory()->count(10)->create([
            'created_at' => now()->subDays(3),
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', '7');

        $totalOrders = $component->get('totalOrders');

        expect($totalOrders)->toBe(10);
    });

    it('filters total orders by period', function () {
        Order::factory()->count(5)->create([
            'created_at' => now()->subDays(3),
        ]);

        Order::factory()->count(3)->create([
            'created_at' => now()->subDays(20),
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', '7');

        $totalOrders = $component->get('totalOrders');

        expect($totalOrders)->toBe(5);
    });

    it('filters total orders by channel', function () {
        Order::factory()->count(3)->create([
            'created_at' => now()->subDays(3),
            'channel_name' => 'Amazon',
        ]);

        Order::factory()->count(2)->create([
            'created_at' => now()->subDays(3),
            'channel_name' => 'eBay',
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', '7')
            ->set('channel', 'Amazon');

        $totalOrders = $component->get('totalOrders');

        expect($totalOrders)->toBe(3);
    });

    it('handles custom date range for total orders', function () {
        Order::factory()->count(3)->create([
            'created_at' => Carbon::parse('2025-01-05'),
        ]);

        Order::factory()->count(2)->create([
            'created_at' => Carbon::parse('2025-01-20'),
        ]);

        $component = Livewire::test(RecentOrders::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $totalOrders = $component->get('totalOrders');

        expect($totalOrders)->toBe(3);
    });

    it('recent orders are ordered by most recent first', function () {
        $oldOrder = Order::factory()->create([
            'created_at' => now()->subDays(5),
            'order_number' => 'OLD123',
        ]);

        $newOrder = Order::factory()->create([
            'created_at' => now()->subDay(),
            'order_number' => 'NEW456',
        ]);

        $component = Livewire::test(RecentOrders::class);

        $recentOrders = $component->get('recentOrders');

        expect($recentOrders->first()->order_number)->toBe('NEW456')
            ->and($recentOrders->last()->order_number)->toBe('OLD123');
    });
});
