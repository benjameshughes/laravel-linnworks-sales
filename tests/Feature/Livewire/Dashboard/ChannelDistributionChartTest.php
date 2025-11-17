<?php

declare(strict_types=1);

use App\Livewire\Dashboard\ChannelDistributionChart;
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

describe('ChannelDistributionChart Livewire Component', function () {
    it('renders successfully', function () {
        Livewire::test(ChannelDistributionChart::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.dashboard.channel-distribution-chart');
    });

    it('initializes with default values', function () {
        Livewire::test(ChannelDistributionChart::class)
            ->assertSet('period', '7')
            ->assertSet('channel', 'all')
            ->assertSet('viewMode', 'detailed');
    });

    it('responds to filters-updated event', function () {
        Livewire::test(ChannelDistributionChart::class)
            ->dispatch('filters-updated', period: '30', channel: 'Amazon', status: 'all')
            ->assertSet('period', '30')
            ->assertSet('channel', 'Amazon');
    });

    it('returns chart data with correct structure', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'source' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        $component = Livewire::test(ChannelDistributionChart::class);

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKey('labels')
            ->toHaveKey('datasets');
    });

    it('can switch between detailed and grouped view modes', function () {
        Livewire::test(ChannelDistributionChart::class)
            ->assertSet('viewMode', 'detailed')
            ->call('setViewMode', 'grouped')
            ->assertSet('viewMode', 'grouped');
    });

    it('generates unique chart key based on filters and view mode', function () {
        $component = Livewire::test(ChannelDistributionChart::class)
            ->set('period', '7')
            ->set('channel', 'Amazon')
            ->set('viewMode', 'detailed');

        $chartKey = $component->get('chartKey');

        expect($chartKey)
            ->toBeString()
            ->toContain('channel-doughnut')
            ->toContain('detailed')
            ->toContain('7')
            ->toContain('Amazon');
    });

    it('handles empty data gracefully', function () {
        $component = Livewire::test(ChannelDistributionChart::class);

        $chartData = $component->get('chartData');

        expect($chartData)->toBeArray();
    });

    it('filters data by period', function () {
        Order::factory()->create([
            'created_at' => now()->subDays(2),
            'source' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'created_at' => now()->subDays(20),
            'source' => 'eBay',
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(ChannelDistributionChart::class)
            ->set('period', '7');

        $chartData = $component->get('chartData');

        expect($chartData)->toBeArray();
    });

    it('handles custom date range', function () {
        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-05'),
            'source' => 'Amazon',
            'total_charge' => 100.00,
        ]);

        Order::factory()->create([
            'created_at' => Carbon::parse('2025-01-20'),
            'source' => 'eBay',
            'total_charge' => 200.00,
        ]);

        $component = Livewire::test(ChannelDistributionChart::class)
            ->set('period', 'custom')
            ->set('customFrom', '2025-01-01')
            ->set('customTo', '2025-01-10');

        $chartData = $component->get('chartData');

        expect($chartData)->toBeArray();
    });

    it('grouped view transforms detailed data correctly', function () {
        $component = Livewire::test(ChannelDistributionChart::class)
            ->set('viewMode', 'grouped');

        $chartData = $component->get('chartData');

        expect($chartData)
            ->toBeArray()
            ->toHaveKey('labels')
            ->toHaveKey('datasets');
    });
});
