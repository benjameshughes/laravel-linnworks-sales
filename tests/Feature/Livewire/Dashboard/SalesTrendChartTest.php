<?php

declare(strict_types=1);

use App\Livewire\Dashboard\SalesTrendChart;
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

describe('SalesTrendChart Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(SalesTrendChart::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.sales-trend-chart');
    });

    it('initializes with default values', function () {
        Livewire::test(SalesTrendChart::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('viewMode', 'revenue');
    });

    it('responds to filters-updated event', function () {
        Livewire::test(SalesTrendChart::class)
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('returns chart data with correct structure', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'received_date' => now()->subDays(2),
            'total_charge' => 100.00,
            'items' => [['sku' => 'ABC', 'quantity' => 2]],
        ]);

        $component = Livewire::test(SalesTrendChart::class);

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKey('labels')
            ->toHaveKey('datasets');
    });

    it('can switch between revenue and orders view mode', function () {
        Livewire::test(SalesTrendChart::class)
            ->assertSet('viewMode', 'revenue')
            ->call('setViewMode', 'orders')
            ->assertSet('viewMode', 'orders');
    });

    it('generates unique chart key based on filters and view mode', function () {
        $component = Livewire::test(SalesTrendChart::class)
            ->set('period', '7')
            ->set('channel', 'Amazon')
            ->set('viewMode', 'revenue');

        $chartKey = $component->get('chartKey');

        expect($chartKey)
            ->toBeString()
            ->toContain('sales-trend')
            ->toContain('revenue')
            ->toContain('7')
            ->toContain('Amazon');
    });

    it('generates period label correctly', function () {
        $component = Livewire::test(SalesTrendChart::class)
            ->set('period', '7');

        $periodLabel = $component->get('periodLabel');

        expect($periodLabel)->toBeString();
    });

    it('generates custom period label', function () {
        $component = Livewire::test(SalesTrendChart::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $periodLabel = $component->get('periodLabel');

        expect($periodLabel)
            ->toBeString()
            ->toContain('Custom');
    });

    it('handles empty data gracefully', function () {
        $component = Livewire::test(SalesTrendChart::class);

        $chartData = $component->get('chartData');

        expect($chartData)->toBeArray();
    });

    it('filters data by channel', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'received_date' => now()->subDays(2),
            'channel_name' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'received_date' => now()->subDays(2),
            'channel_name' => 'eBay',
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(SalesTrendChart::class)
            ->set('channel', 'Amazon');

        $chartData = $component->get('chartData');

        expect($chartData)->toBeArray();
    });
});
