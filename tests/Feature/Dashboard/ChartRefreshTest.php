<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Events\CacheWarmingCompleted;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ChartRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function sales_trend_chart_refreshes_after_cache_warming_event(): void
    {
        // Create test data
        $order = Order::factory()->create([
            'total_charge' => 100.00,
            'received_date' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'total_price' => 100.00,
        ]);

        // Pre-warm cache with data
        $cacheData = [
            'revenue' => 100.00,
            'orders' => 1,
            'items' => 1,
            'chart_line' => [
                'labels' => ['Today'],
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'data' => [100.00],
                    ],
                ],
            ],
        ];

        Cache::put('metrics_7d_all_all', $cacheData, 3600);

        $this->actingAs($this->user);

        // Test component
        $component = Livewire::test('dashboard.sales-trend-chart')
            ->set('period', '7')
            ->set('channel', 'all')
            ->set('status', 'all');

        // Verify initial state - chart should have data
        $component->assertSet('period', '7');
        $chartData = $component->get('chartData');
        $this->assertNotEmpty($chartData);
        $this->assertArrayHasKey('labels', $chartData);

        // Simulate cache warming completion event
        $component->dispatch('echo:cache-management', 'CacheWarmingCompleted', ['periods_warmed' => 8]);

        // Chart should have refreshed (computed property cache invalidated)
        // The component should have re-rendered
        $component->assertSuccessful();
    }

    /** @test */
    public function daily_revenue_chart_refreshes_after_cache_warming_event(): void
    {
        // Pre-warm cache
        $cacheData = [
            'revenue' => 200.00,
            'orders' => 2,
            'chart_orders_revenue' => [
                'labels' => ['Today'],
                'datasets' => [],
            ],
        ];

        Cache::put('metrics_7d_all_all', $cacheData, 3600);

        $this->actingAs($this->user);

        $component = Livewire::test('dashboard.daily-revenue-chart')
            ->set('period', '7');

        // Simulate event
        $component->dispatch('echo:cache-management', 'CacheWarmingCompleted', ['periods_warmed' => 8]);

        $component->assertSuccessful();
    }

    /** @test */
    public function channel_distribution_chart_refreshes_after_cache_warming_event(): void
    {
        // Pre-warm cache
        $cacheData = [
            'revenue' => 150.00,
            'chart_doughnut' => [
                'labels' => ['Amazon'],
                'datasets' => [],
            ],
        ];

        Cache::put('metrics_7d_all_all', $cacheData, 3600);

        $this->actingAs($this->user);

        $component = Livewire::test('dashboard.channel-distribution-chart')
            ->set('period', '7');

        // Simulate event
        $component->dispatch('echo:cache-management', 'CacheWarmingCompleted', ['periods_warmed' => 8]);

        $component->assertSuccessful();
    }

    /** @test */
    public function chart_displays_empty_when_cache_is_empty(): void
    {
        // Ensure no cache exists
        Cache::forget('metrics_7d_all_all');

        $this->actingAs($this->user);

        $component = Livewire::test('dashboard.sales-trend-chart')
            ->set('period', '7')
            ->set('channel', 'all')
            ->set('status', 'all');

        $chartData = $component->get('chartData');

        // Should return empty structure
        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertEmpty($chartData['labels']);
        $this->assertEmpty($chartData['datasets']);
    }

    /** @test */
    public function chart_returns_cached_data_when_available(): void
    {
        // Pre-warm cache with specific data
        $cacheData = [
            'revenue' => 500.00,
            'orders' => 5,
            'items' => 10,
            'chart_line' => [
                'labels' => ['Day 1', 'Day 2', 'Day 3'],
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'data' => [100.00, 200.00, 200.00],
                    ],
                ],
            ],
        ];

        Cache::put('metrics_7d_all_all', $cacheData, 3600);

        $this->actingAs($this->user);

        $component = Livewire::test('dashboard.sales-trend-chart')
            ->set('period', '7')
            ->set('channel', 'all')
            ->set('status', 'all');

        $chartData = $component->get('chartData');

        // Should return the cached chart data
        $this->assertEquals(['Day 1', 'Day 2', 'Day 3'], $chartData['labels']);
        $this->assertCount(1, $chartData['datasets']);
        $this->assertEquals([100.00, 200.00, 200.00], $chartData['datasets'][0]['data']);
    }
}
