<?php

declare(strict_types=1);

use App\Livewire\Dashboard\MetricsSummary;
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

describe('MetricsSummary Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(MetricsSummary::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.metrics-summary');
    });

    it('initializes with default period and channel', function () {
        Livewire::test(MetricsSummary::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('status', 'all');
    });

    it('initializes from query parameters', function () {
        $this->withoutExceptionHandling();

        Livewire::withQueryParams(['period' => '30', 'channel' => 'Amazon'])
            ->test(MetricsSummary::class)
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('updates filters when filters-updated event is dispatched', function () {
        Livewire::test(MetricsSummary::class)
            ->assertSet('period', '7')
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('computes metrics correctly with real data', function () {
        Order::factory()->count(5)->create([
            'created_at' => now()->subDays(3),
            'total_charge' => 100.00,
            'items' => [
                ['sku' => 'ABC123', 'quantity' => 2],
            ],
        ]);

        $component = Livewire::test(MetricsSummary::class)
            ->set('period', '7');

        $metrics = $component->get('metrics');

        expect($metrics)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($metrics['total_revenue'])
            ->toBe(500.00)
            ->and($metrics['total_orders'])
            ->toBe(5)
            ->and($metrics['total_items'])
            ->toBe(10);
    });

    it('filters metrics by channel', function () {
        Order::factory()->count(3)->create([
            'created_at' => now()->subDays(3),
            'channel_name' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(2)->create([
            'created_at' => now()->subDays(3),
            'channel_name' => 'eBay',
            'total_charge' => 50.00,
        ]);

        $component = Livewire::test(MetricsSummary::class)
            ->set('period', '7')
            ->set('channel', 'Amazon');

        $metrics = $component->get('metrics');

        expect($metrics['total_orders'])->toBe(3)
            ->and($metrics['total_revenue'])->toBe(300.00);
    });

    it('handles custom date range', function () {
        Order::factory()->count(3)->create([
            'created_at' => Carbon::parse('2025-01-05'),
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(2)->create([
            'created_at' => Carbon::parse('2025-01-20'),
            'total_charge' => 100.00,
        ]);

        $component = Livewire::test(MetricsSummary::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $metrics = $component->get('metrics');

        expect($metrics['total_orders'])->toBe(3)
            ->and($metrics['total_revenue'])->toBe(300.00);
    });

    it('returns zero metrics when no data exists', function () {
        $component = Livewire::test(MetricsSummary::class);

        $metrics = $component->get('metrics');

        expect($metrics['total_revenue'])->toBe(0)
            ->and($metrics['total_orders'])->toBe(0)
            ->and($metrics['total_items'])->toBe(0);
    });

    it('updates metrics when filters change', function () {
        Order::factory()->count(5)->create([
            'created_at' => now()->subDays(3),
            'total_charge' => 100.00,
        ]);

        Order::factory()->count(3)->create([
            'created_at' => now()->subDays(20),
            'total_charge' => 100.00,
        ]);

        $component = Livewire::test(MetricsSummary::class)
            ->set('period', '7');

        $metrics = $component->get('metrics');
        expect($metrics['total_orders'])->toBe(5);

        $component->set('period', '30');
        $metricsAfter = $component->get('metrics');
        expect($metricsAfter['total_orders'])->toBe(8);
    });
});
