<?php

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use Livewire\Livewire;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use App\Enums\ComparisonMode;
use App\Livewire\Dashboard\ChannelComparison;
use App\Services\Metrics\ChannelComparisonQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedOrder(string $receivedAt, string $source, float $charge, array $overrides = []): Order
{
    return Order::factory()->create(array_merge([
        'received_at' => $receivedAt,
        'source' => $source,
        'subsource' => null,
        'total_charge' => $charge,
        'status' => 1,
        'is_paid' => true,
        'is_cancelled' => false,
    ], $overrides));
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    // Anchor month: June 2025 -> £300 across two channels
    seedOrder('2025-06-05 10:00:00', 'AMAZON', 100.00);
    seedOrder('2025-06-12 10:00:00', 'AMAZON', 100.00);
    seedOrder('2025-06-20 10:00:00', 'EBAY', 100.00);

    // MoM baseline: May 2025 -> £50 on AMAZON only
    seedOrder('2025-05-10 10:00:00', 'AMAZON', 50.00);

    // YoY baseline: June 2024 -> £25 on AMAZON only
    seedOrder('2024-06-10 10:00:00', 'AMAZON', 25.00);
});

it('renders the channel comparison page at dashboard/channel', function () {
    $this->get('/dashboard/channel')->assertOk()->assertSee('Channel Comparison');
});

it('redirects the legacy channels url to the new dashboard route', function () {
    $this->get('/channels')->assertRedirect('dashboard/channel');
});

it('requires authentication', function () {
    auth()->logout();

    $this->get('/dashboard/channel')->assertRedirect(route('login'));
});

it('defaults the anchor to the latest month holding data', function () {
    Livewire::test(ChannelComparison::class)
        ->assertSet('month', '2025-06')
        ->assertSet('mode', ComparisonMode::MonthOverMonth->value);
});

it('compares the anchor month against the previous month in MoM mode', function () {
    $component = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::MonthOverMonth->value);

    $totals = $component->instance()->totals;

    expect($totals['current_revenue'])->toBe(300.0)
        ->and($totals['baseline_revenue'])->toBe(50.0)
        ->and($totals['revenue_indicator']->percentage)->toBe(500.0)
        ->and($totals['revenue_indicator']->label())->toBe('+500.0%');
});

it('compares the anchor month against the same month last year in YoY mode', function () {
    $component = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::YearOverYear->value);

    $totals = $component->instance()->totals;

    expect($totals['current_revenue'])->toBe(300.0)
        ->and($totals['baseline_revenue'])->toBe(25.0)
        ->and($totals['revenue_indicator']->percentage)->toBe(1100.0);
});

it('marks a channel with no baseline volume as new', function () {
    $channels = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::MonthOverMonth->value)
        ->instance()->channels;

    $ebay = $channels->firstWhere('source', 'EBAY');

    expect($ebay->isNew())->toBeTrue()
        ->and($ebay->revenueGrowth())->toBeNull();
});

it('includes channels that only traded in the baseline period', function () {
    seedOrder('2025-05-15 10:00:00', 'TESCO', 80.00);

    $channels = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::MonthOverMonth->value)
        ->instance()->channels;

    $tesco = $channels->firstWhere('source', 'TESCO');

    expect($tesco)->not->toBeNull()
        ->and($tesco->isLost())->toBeTrue()
        ->and($tesco->baselineRevenue)->toBe(80.0);
});

it('excludes DIRECT orders to stay consistent with the main dashboard', function () {
    seedOrder('2025-06-08 10:00:00', 'DIRECT', 999.00);

    $totals = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->instance()->totals;

    expect($totals['current_revenue'])->toBe(300.0);
});

it('splits channels by subsource when the toggle is enabled', function () {
    seedOrder('2025-06-25 10:00:00', 'AMAZON', 40.00, ['subsource' => 'Amazon UK']);

    $component = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->call('toggleSubsources');

    expect($component->get('includeSubsources'))->toBeTrue();

    $names = $component->instance()->channels->map(fn ($channel) => $channel->name);

    expect($names)->toContain('Amazon UK (Amazon)');
});

it('filters to a single source when requested', function () {
    $totals = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('source', 'EBAY')
        ->instance()->totals;

    expect($totals['current_revenue'])->toBe(100.0)
        ->and($totals['current_orders'])->toBe(1);
});

it('respects the status filter', function () {
    seedOrder('2025-06-28 10:00:00', 'AMAZON', 500.00, ['status' => 0, 'is_paid' => false]);

    $totals = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('status', 'processed')
        ->instance()->totals;

    expect($totals['current_revenue'])->toBe(300.0);
});

it('counts items sold from the order items table', function () {
    $order = seedOrder('2025-06-18 10:00:00', 'TESCO', 60.00);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 3]);

    $channels = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->instance()->channels;

    expect($channels->firstWhere('source', 'TESCO')->currentItems)->toBe(3);
});

it('sorts channels by the selected column', function () {
    $byRevenue = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('sortBy', 'revenue')
        ->instance()->channels;

    expect($byRevenue->first()->source)->toBe('AMAZON');

    $byGrowth = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('sortBy', 'growth')
        ->instance()->channels;

    // EBAY has no baseline so its growth is unmeasurable and sinks to the bottom
    expect($byGrowth->last()->source)->toBe('EBAY');
});

it('builds a daily overlay covering the longest of the two months', function () {
    $trend = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::MonthOverMonth->value)
        ->instance()->comparison['trend'];

    // May has 31 days, June has 30 - the axis must stretch to 31
    expect($trend['labels'])->toHaveCount(31)
        ->and($trend['current'][4])->toBe(100.0)   // 5 June
        ->and($trend['baseline'][9])->toBe(50.0);  // 10 May
});

it('produces chart payloads for both visualisations', function () {
    $component = Livewire::test(ChannelComparison::class)->set('month', '2025-06');

    $revenueChart = $component->instance()->revenueChart;
    $trendChart = $component->instance()->trendChart;

    expect($revenueChart['datasets'])->toHaveCount(2)
        ->and($revenueChart['datasets'][0]['label'])->toBe('June 2025')
        ->and($revenueChart['datasets'][1]['label'])->toBe('May 2025')
        ->and($revenueChart['labels'])->toContain('Amazon')
        ->and($trendChart['datasets'])->toHaveCount(2);
});

it('lists only months that actually contain orders', function () {
    $months = ChannelComparisonQuery::availableMonths();

    expect($months->keys()->all())->toBe(['2025-06', '2025-05', '2024-06'])
        ->and($months->get('2025-06'))->toBe('June 2025');
});

it('resolves the baseline month for each comparison mode', function () {
    $anchor = CarbonImmutable::create(2025, 6, 1);

    expect(ComparisonMode::MonthOverMonth->baseline($anchor)->format('Y-m'))->toBe('2025-05')
        ->and(ComparisonMode::YearOverYear->baseline($anchor)->format('Y-m'))->toBe('2024-06');
});

it('falls back to the latest data month when the query string month is malformed', function () {
    $this->get('/dashboard/channel?month=not-a-month')->assertOk();

    Livewire::withQueryParams(['month' => 'not-a-month'])
        ->test(ChannelComparison::class)
        ->assertSet('month', '2025-06');
});

it('rejects an out-of-range month instead of silently overflowing', function () {
    // "2025-13" would parse as January 2026 if only the shape were checked
    expect(ChannelComparisonQuery::parseMonth('2025-13'))->toBeNull()
        ->and(ChannelComparisonQuery::parseMonth('2025-00'))->toBeNull()
        ->and(ChannelComparisonQuery::parseMonth(''))->toBeNull()
        ->and(ChannelComparisonQuery::parseMonth('9999-99'))->toBeNull()
        ->and(ChannelComparisonQuery::parseMonth('2025-06')?->format('Y-m'))->toBe('2025-06');
});

it('normalises an out-of-range query string month so the picker matches the heading', function () {
    Livewire::withQueryParams(['month' => '2025-13'])
        ->test(ChannelComparison::class)
        ->assertSet('month', '2025-06')
        ->assertSee('June 2025');
});

it('defaults to a month whose baseline also holds data', function () {
    // January 2026 is the newest month, but December 2025 is empty, so
    // anchoring there would report every channel as new
    seedOrder('2026-01-10 10:00:00', 'AMAZON', 400.00);

    expect(ChannelComparisonQuery::availableMonths()->keys()->first())->toBe('2026-01');

    Livewire::test(ChannelComparison::class)
        ->assertSet('month', '2025-06');
});

it('falls back to the latest month when no pair of months qualifies', function () {
    Order::query()->delete();
    seedOrder('2026-01-10 10:00:00', 'AMAZON', 400.00);

    expect(ChannelComparisonQuery::defaultAnchor(ComparisonMode::MonthOverMonth)->format('Y-m'))->toBe('2026-01');
});

it('leaves days the month never had as null so the chart breaks the line', function () {
    // June has 30 days, May has 31 - day 31 does not exist for the anchor
    $trend = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::MonthOverMonth->value)
        ->instance()->comparison['trend'];

    expect($trend['current'])->toHaveCount(31)
        ->and($trend['current'][30])->toBeNull()
        ->and($trend['current'][29])->toBe(0.0)
        ->and($trend['baseline'][30])->toBe(0.0);
});

it('leaves future days of an in-progress month as null', function () {
    Carbon::setTestNow(Carbon::parse('2025-06-10 12:00:00'));

    $component = Livewire::test(ChannelComparison::class)->set('month', '2025-06');
    $trend = $component->instance()->comparison['trend'];

    expect($trend['current'][9])->not->toBeNull()   // 10 June, today
        ->and($trend['current'][10])->toBeNull()    // 11 June, not yet happened
        ->and($component->instance()->isInProgress)->toBeTrue()
        ->and($component->instance()->progressLabel)->toBe('10 of 30 days');

    Carbon::setTestNow();
});

it('does not flag a completed month as in progress', function () {
    expect(
        Livewire::test(ChannelComparison::class)->set('month', '2025-06')->instance()->isInProgress
    )->toBeFalse();
});

it('reports when the baseline period has no orders at all', function () {
    $withBaseline = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->set('mode', ComparisonMode::MonthOverMonth->value);

    expect($withBaseline->instance()->hasBaselineData)->toBeTrue();

    // April 2025 has no orders, so May 2025 has nothing to measure against
    $withoutBaseline = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-05')
        ->set('mode', ComparisonMode::MonthOverMonth->value);

    expect($withoutBaseline->instance()->hasBaselineData)->toBeFalse();
    $withoutBaseline->assertSee('No orders were recorded in');
});

it('rounds the trend series without tripping over nulls', function () {
    $datasets = Livewire::test(ChannelComparison::class)
        ->set('month', '2025-06')
        ->instance()->trendChart['datasets'];

    expect($datasets[0]['data'][30])->toBeNull()
        ->and($datasets[0]['data'][4])->toBe(100.0);
});
