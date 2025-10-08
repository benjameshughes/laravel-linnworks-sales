<?php

use App\Livewire\Analytics\SalesAnalytics;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Carbon::setTestNow(Carbon::parse('2024-11-10 12:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('exposes chart data for selected day ranges', function () {
    $first = Order::factory()->create([
        'total_charge' => 100,
        'received_date' => now()->copy()->subDays(2)->setTime(10, 15),
        'is_processed' => true,
    ]);

    $last = Order::factory()->create([
        'total_charge' => 60,
        'received_date' => now()->copy()->subDay()->setTime(9, 0),
        'is_processed' => false,
    ]);

    $component = Livewire::test(SalesAnalytics::class)
        ->set('datePreset', 'custom')
        ->set('startDate', now()->copy()->subDays(2)->toDateString())
        ->set('endDate', now()->copy()->subDay()->toDateString());

    $chartData = $component->get('chartData');

    expect($chartData['labels'])->toHaveCount(2);
    expect(array_map('floatval', $chartData['datasets'][0]['data']))->toBe([100.0, 60.0]);
});
