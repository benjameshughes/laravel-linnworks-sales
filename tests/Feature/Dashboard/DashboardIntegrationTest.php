<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /** @test */
    public function dashboard_loads_successfully_with_real_data(): void
    {
        // Create test data with MySQL schema
        $product = Product::factory()->create([
            'sku' => 'TEST-SKU-001',
            'title' => 'Test Product',
            'category_name' => 'Electronics',
            'purchase_price' => 50.00,
            'stock_available' => 100,
            'stock_minimum' => 10,
        ]);

        $order = Order::factory()->create([
            'order_number' => 'ORD-12345',
            'channel_name' => 'Amazon',
            'subsource' => 'Amazon UK',
            'total_charge' => 150.00,
            'received_date' => now()->subDays(1),
            'is_open' => true,
            'is_processed' => false,
        ]);

        // Create order items using NEW field names
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'title' => $product->title,
            'quantity' => 2,
            'unit_price' => 75.00,
            'total_price' => 150.00,
            'cost_price' => 50.00,
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/');

        $response->assertSuccessful();
        $response->assertSeeLivewire('dashboard');
    }

    /** @test */
    public function dashboard_displays_correct_metrics(): void
    {
        $this->createTestOrders();

        $this->actingAs($this->user);

        Livewire::test('dashboard')
            ->assertSet('period', '30')
            ->call('$refresh')
            ->assertSuccessful();
    }

    /** @test */
    public function dashboard_recent_orders_uses_correct_field_names(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'ORD-99999',
            'channel_name' => 'eBay',
            'subsource' => 'eBay UK',
            'total_charge' => 99.99,
            'received_date' => now(),
            'is_open' => true,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => 'TEST-001',
            'quantity' => 1,
            'unit_price' => 99.99,
            'total_price' => 99.99,
        ]);

        $this->actingAs($this->user);

        Livewire::test('dashboard.recent-orders')
            ->assertSee('ORD-99999')
            ->assertSee('eBay')
            ->assertSee('eBay UK') // Testing subsource field
            ->assertSee('99.99');
    }

    /** @test */
    public function product_repository_returns_correct_sales_data(): void
    {
        $product = Product::factory()->create(['sku' => 'REPO-TEST-001']);

        $order = Order::factory()->create(['received_date' => now()]);

        // Test with NEW field names
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 5,
            'unit_price' => 20.00,
            'total_price' => 100.00,
            'cost_price' => 10.00,
        ]);

        $repository = app(\App\Repositories\ProductRepository::class);
        $salesData = $repository->getProductSalesData($product->sku);

        $this->assertEquals(5, $salesData['total_sold']);
        $this->assertEquals(100.00, $salesData['total_revenue']);
        $this->assertEquals(20.00, $salesData['avg_selling_price']);
        $this->assertEquals(1, $salesData['order_count']);
    }

    /** @test */
    public function product_repository_handles_zero_unit_price_correctly(): void
    {
        $product = Product::factory()->create(['sku' => 'ZERO-PRICE-001']);
        $order = Order::factory()->create();

        // Item with total_price but zero unit_price
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 3,
            'unit_price' => 0.00,
            'total_price' => 90.00,
            'cost_price' => 30.00,
        ]);

        $repository = app(\App\Repositories\ProductRepository::class);
        $salesData = $repository->getProductSalesData($product->sku);

        $this->assertEquals(3, $salesData['total_sold']);
        $this->assertEquals(90.00, $salesData['total_revenue']);
        $this->assertEquals(30.00, $salesData['avg_selling_price']); // Should calculate from total_price / quantity
    }

    /** @test */
    public function sales_metrics_calculates_total_items_sold_correctly(): void
    {
        $orders = $this->createTestOrders();

        $metrics = new \App\Services\Metrics\SalesMetrics($orders);
        $totalItems = $metrics->totalItemsSold();

        // Assert it returns an INT, not a string
        $this->assertIsInt($totalItems);
        $this->assertGreaterThan(0, $totalItems);
    }

    /** @test */
    public function sales_metrics_calculates_revenue_from_order_items_table(): void
    {
        $order = Order::factory()->create([
            'total_charge' => 0, // Force it to calculate from order_items
            'received_date' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 50.00,
            'total_price' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total_price' => 25.00,
        ]);

        $metrics = new \App\Services\Metrics\SalesMetrics(collect([$order->load('orderItems')]));
        $revenue = $metrics->totalRevenue();

        $this->assertEquals(125.00, $revenue);
    }

    /** @test */
    public function product_metrics_uses_correct_field_names(): void
    {
        $product = Product::factory()->create(['sku' => 'METRICS-001']);
        $order = Order::factory()->create(['received_date' => now()]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 10,
            'unit_price' => 15.00,
            'total_price' => 150.00,
            'cost_price' => 8.00,
        ]);

        $items = collect([$item]);
        $metrics = new \App\Services\Metrics\ProductMetrics($items);

        $this->assertEquals(150.00, $metrics->totalProductRevenue());
        $this->assertEquals(10, $metrics->totalProductsSold());
        $this->assertEquals(15.00, $metrics->averageProductPrice());
    }

    /** @test */
    public function product_badge_service_calculates_with_correct_fields(): void
    {
        $product = Product::factory()->create([
            'sku' => 'BADGE-TEST-001',
            'created_at' => now()->subDays(5), // Recent product
        ]);

        $order = Order::factory()->create(['received_date' => now()]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 100,
            'unit_price' => 50.00,
            'total_price' => 5000.00,
            'cost_price' => 20.00,
        ]);

        $service = app(\App\Services\ProductBadgeService::class);
        $badges = $service->getProductBadges($product, 30);

        $this->assertNotEmpty($badges);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $badges);
    }

    /** @test */
    public function channel_comparison_component_loads_with_real_data(): void
    {
        // Create orders across different channels
        $order1 = Order::factory()->create([
            'channel_name' => 'Amazon',
            'subsource' => 'Amazon UK',
            'total_charge' => 150.00,
            'received_date' => now(),
        ]);

        $order2 = Order::factory()->create([
            'channel_name' => 'eBay',
            'subsource' => null,
            'total_charge' => 75.00,
            'received_date' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'sku' => 'CH-001',
            'quantity' => 2,
            'total_price' => 150.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'sku' => 'CH-002',
            'quantity' => 1,
            'total_price' => 75.00,
        ]);

        $this->actingAs($this->user);

        Livewire::test('dashboard.channel-comparison')
            ->assertSuccessful()
            ->assertSee('Amazon')
            ->assertSee('eBay');
    }

    /** @test */
    public function product_detail_page_displays_correct_metrics(): void
    {
        $product = Product::factory()->create([
            'sku' => 'DETAIL-001',
            'title' => 'Test Product Detail',
            'stock_available' => 50,
        ]);

        $order = Order::factory()->create(['received_date' => now()]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 3,
            'unit_price' => 25.00,
            'total_price' => 75.00,
            'cost_price' => 15.00,
        ]);

        $this->actingAs($this->user);

        Livewire::test('product-detail', ['sku' => $product->sku])
            ->assertSuccessful()
            ->assertSee('Test Product Detail')
            ->assertSee('75.00'); // Should see revenue
    }

    /** @test */
    public function dashboard_handles_orders_with_no_items_gracefully(): void
    {
        // Create order without any items
        Order::factory()->create([
            'order_number' => 'EMPTY-001',
            'total_charge' => 0,
            'received_date' => now(),
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/');

        $response->assertSuccessful();
    }

    /** @test */
    public function repository_handles_missing_subsource_field(): void
    {
        $order = Order::factory()->create([
            'channel_name' => 'Direct',
            'subsource' => null, // No subsource
            'received_date' => now(),
        ]);

        $this->actingAs($this->user);

        Livewire::test('dashboard.recent-orders')
            ->assertSee('Direct')
            ->assertDontSee('undefined')
            ->assertDontSee('sub_source'); // Old field name should not appear
    }

    /** @test */
    public function product_repository_daily_sales_uses_correct_fields(): void
    {
        $product = Product::factory()->create(['sku' => 'DAILY-001']);
        $order = Order::factory()->create(['received_date' => now()]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 5,
            'unit_price' => 20.00,
            'total_price' => 100.00,
        ]);

        $repository = app(\App\Repositories\ProductRepository::class);
        $dailySales = $repository->getProductDailySales($product->sku, now()->startOfDay());

        $this->assertNotEmpty($dailySales);
        $dailySale = $dailySales->first();
        $this->assertEquals(5, $dailySale->quantity);
        $this->assertEquals(100.00, $dailySale->revenue);
    }

    /** @test */
    public function product_repository_channel_performance_uses_correct_fields(): void
    {
        $product = Product::factory()->create(['sku' => 'CHANNEL-001']);
        $order = Order::factory()->create([
            'channel_name' => 'Amazon',
            'received_date' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product->sku,
            'quantity' => 10,
            'unit_price' => 30.00,
            'total_price' => 300.00,
        ]);

        $repository = app(\App\Repositories\ProductRepository::class);
        $performance = $repository->getProductChannelPerformance($product->sku);

        $this->assertNotEmpty($performance);
        $channelData = $performance->first();
        $this->assertEquals('Amazon', $channelData['channel']);
        $this->assertEquals(10, $channelData['quantity_sold']);
        $this->assertEquals(300.00, $channelData['revenue']);
    }

    /** @test */
    public function bulk_product_sales_data_returns_correct_types(): void
    {
        $product1 = Product::factory()->create(['sku' => 'BULK-001']);
        $product2 = Product::factory()->create(['sku' => 'BULK-002']);

        $order = Order::factory()->create(['received_date' => now()]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product1->sku,
            'quantity' => 5,
            'total_price' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'sku' => $product2->sku,
            'quantity' => 3,
            'total_price' => 60.00,
        ]);

        $repository = app(\App\Repositories\ProductRepository::class);
        $bulkData = $repository->getBulkProductSalesData(['BULK-001', 'BULK-002', 'BULK-003']);

        // Should have data for all requested SKUs
        $this->assertCount(3, $bulkData);

        // Check data types
        $this->assertIsInt($bulkData['BULK-001']['total_sold']);
        $this->assertIsFloat($bulkData['BULK-001']['total_revenue']);
        $this->assertIsFloat($bulkData['BULK-001']['avg_selling_price']);
        $this->assertIsInt($bulkData['BULK-001']['order_count']);

        // SKU with no sales should have zeros
        $this->assertEquals(0, $bulkData['BULK-003']['total_sold']);
        $this->assertEquals(0.0, $bulkData['BULK-003']['total_revenue']);
    }

    private function createTestOrders(): \Illuminate\Support\Collection
    {
        $product1 = Product::factory()->create(['sku' => 'TEST-001']);
        $product2 = Product::factory()->create(['sku' => 'TEST-002']);

        $order1 = Order::factory()->create([
            'channel_name' => 'Amazon',
            'subsource' => 'Amazon UK',
            'total_charge' => 200.00,
            'received_date' => now()->subDays(5),
            'is_open' => false,
            'is_processed' => true,
        ]);

        $order2 = Order::factory()->create([
            'channel_name' => 'eBay',
            'subsource' => null,
            'total_charge' => 150.00,
            'received_date' => now()->subDays(3),
            'is_open' => true,
            'is_processed' => false,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'sku' => $product1->sku,
            'quantity' => 4,
            'unit_price' => 50.00,
            'total_price' => 200.00,
            'cost_price' => 30.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'sku' => $product2->sku,
            'quantity' => 3,
            'unit_price' => 50.00,
            'total_price' => 150.00,
            'cost_price' => 25.00,
        ]);

        return Order::with('orderItems')->get();
    }
}
