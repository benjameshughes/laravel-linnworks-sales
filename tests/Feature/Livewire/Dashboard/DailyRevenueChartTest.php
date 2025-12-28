<?php

declare(strict_types=1);

use App\Livewire\Dashboard\DailyRevenueChart;
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

describe('DailyRevenueChart Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(DailyRevenueChart::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.daily-revenue-chart');
    });

    it('initializes with default values', function () {
        Livewire::test(DailyRevenueChart::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('viewMode', 'orders_revenue');
    });

    it('responds to filters-updated event', function () {
        Livewire::test(DailyRevenueChart::class)
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('returns chart data with correct structure', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'received_at' => now()->subDays(2),
            'total_charge' => 100.00,
        ]);

        $component = Livewire::test(DailyRevenueChart::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'));

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKeys(['labels', 'datasets']);
    });

    it('handles empty data gracefully', function () {
        $component = Livewire::test(DailyRevenueChart::class);

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKeys(['labels', 'datasets']);
    });

    it('generates period label for numeric periods', function () {
        $component = Livewire::test(DailyRevenueChart::class)
            ->set('period', '7');

        $periodLabel = $component->get('periodLabel');

        expect($periodLabel)->toBeString();
    });

    it('generates period label for custom periods', function () {
        $component = Livewire::test(DailyRevenueChart::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $periodLabel = $component->get('periodLabel');

        expect($periodLabel)
            ->toBeString()
            ->toContain('Custom');
    });

    it('filters chart data by channel', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'received_at' => now()->subDays(2),
            'source' => 'amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'received_at' => now()->subDays(2),
            'source' => 'ebay',
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(DailyRevenueChart::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(7)->format('Y-m-d'))
            ->set('customTo', now()->format('Y-m-d'))
            ->set('channel', 'Amazon');

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKeys(['labels', 'datasets']);
    });

    it('handles custom date range', function () {
        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-05'),
            'received_at' => Carbon::parse('2025-01-05'),
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-20'),
            'received_at' => Carbon::parse('2025-01-20'),
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(DailyRevenueChart::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKeys(['labels', 'datasets']);
    });
});
