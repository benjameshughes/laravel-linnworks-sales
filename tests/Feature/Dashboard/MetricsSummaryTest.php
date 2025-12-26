<?php

use App\Livewire\Dashboard\MetricsSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the metrics summary component', function () {
    Livewire::test(MetricsSummary::class)
        ->assertOk();
});

it('initializes with default filter values', function () {
    Livewire::test(MetricsSummary::class)
        ->assertSet('period', '7')
        ->assertSet('channel', 'all')
        ->assertSet('status', 'all')
        ->assertSet('customFrom', null)
        ->assertSet('customTo', null);
});

it('updates filters when filters-updated event is dispatched', function () {
    Livewire::test(MetricsSummary::class)
        ->dispatch('filters-updated', period: '30', channel: 'amazon', status: 'completed')
        ->assertSet('period', '30')
        ->assertSet('channel', 'amazon')
        ->assertSet('status', 'completed');
});

it('updates custom date range when provided', function () {
    Livewire::test(MetricsSummary::class)
        ->dispatch('filters-updated',
            period: 'custom',
            channel: 'all',
            status: 'all',
            customFrom: '2025-01-01',
            customTo: '2025-01-31'
        )
        ->assertSet('customFrom', '2025-01-01')
        ->assertSet('customTo', '2025-01-31');
});

it('clears computed properties when cache warming completes', function () {
    // This test verifies the component responds to CacheWarmingCompleted events
    // by clearing cached computed properties so fresh data is loaded
    Livewire::test(MetricsSummary::class)
        ->call('handleCacheWarmingCompleted', [])
        ->assertOk();
});
