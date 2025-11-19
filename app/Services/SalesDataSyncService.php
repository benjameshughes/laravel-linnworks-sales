<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesDataSyncService
{
    public function __construct(
        private LinnworksApiService $linnworksApi
    ) {}

    /**
     * Sync sales data from Linnworks
     */
    public function syncSalesData(
        ?Carbon $from = null,
        ?Carbon $to = null,
        bool $forceUpdate = false
    ): array {
        $from = $from ?? Carbon::now()->subDays(config('linnworks.sync.default_date_range', 30));
        $to = $to ?? Carbon::now();

        $stats = [
            'orders_processed' => 0,
            'orders_created' => 0,
            'orders_updated' => 0,
            'items_processed' => 0,
            'channels_created' => 0,
            'errors' => 0,
            'start_time' => now(),
        ];

        Log::info('Starting sales data sync', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'force_update' => $forceUpdate,
        ]);

        try {
            // Get all orders with details from Linnworks
            $ordersData = $this->linnworksApi->getAllOrdersWithDetails($from, $to);

            foreach ($ordersData as $orderData) {
                try {
                    $this->processOrder($orderData, $forceUpdate, $stats);
                    $stats['orders_processed']++;
                } catch (\Exception $e) {
                    Log::error('Error processing order', [
                        'order_id' => $orderData['pkOrderID'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }

            $stats['end_time'] = now();
            $stats['duration'] = $stats['end_time']->diffInSeconds($stats['start_time']);

            Log::info('Sales data sync completed', $stats);

            return $stats;
        } catch (\Exception $e) {
            Log::error('Sales data sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $stats['errors']++;
            $stats['end_time'] = now();

            return $stats;
        }
    }

    /**
     * Process a single order from Linnworks API
     */
    private function processOrder(array $orderData, bool $forceUpdate, array &$stats): void
    {
        DB::transaction(function () use ($orderData, $forceUpdate, &$stats) {
            // Process channel first
            $channel = $this->processChannel($orderData, $stats);

            // Check if order already exists
            $existingOrder = Order::where('linnworks_order_id', $orderData['pkOrderID'])->first();

            if ($existingOrder && ! $forceUpdate) {
                return; // Skip if order exists and not forcing update
            }

            // Prepare order data
            $orderAttributes = [
                'linnworks_order_id' => $orderData['pkOrderID'],
                'number' => $orderData['nOrderId'] ?? $orderData['OrderNumber'] ?? '',
                'source' => $orderData['Source'] ?? 'unknown',
                'channel_reference_number' => $orderData['ExternalReference'] ?? null,
                'source' => $orderData['Source'] ?? null,
                'subsource' => $orderData['SubSource'] ?? null,
                'external_reference' => $orderData['ExternalReference'] ?? null,
                'total_value' => $orderData['TotalValue'] ?? 0,
                'total_discount' => $orderData['TotalDiscount'] ?? 0,
                'postage_cost' => $orderData['PostageCost'] ?? 0,
                'total_paid' => $orderData['TotalPaid'] ?? $orderData['TotalValue'] ?? 0,
                'profit_margin' => $orderData['ProfitMargin'] ?? null,
                'currency' => $orderData['Currency'] ?? 'GBP',
                'status' => $this->mapOrderStatus($orderData['OrderStatus'] ?? 'pending'),
                'addresses' => $this->processAddresses($orderData),
                'received_at' => $this->parseDate($orderData['dReceivedDate'] ?? $orderData['ReceivedDate'] ?? now()),
                'processed_date' => $this->parseDate($orderData['dProcessedOn'] ?? $orderData['ProcessedDate'] ?? null),
                'dispatched_date' => $this->parseDate($orderData['dDispatchedDate'] ?? $orderData['DispatchedDate'] ?? null),
                'is_resend' => $orderData['bIsResend'] ?? false,
                'is_exchange' => $orderData['bIsExchange'] ?? false,
                'notes' => $orderData['Notes'] ?? null,
                'raw_data' => config('linnworks.storage.store_raw_data', true) ? $orderData : null,
            ];

            // Create or update order
            if ($existingOrder) {
                $existingOrder->update($orderAttributes);
                $order = $existingOrder;
                $stats['orders_updated']++;
            } else {
                $order = Order::create($orderAttributes);
                $stats['orders_created']++;
            }

            // Process order items
            $this->processOrderItems($order, $orderData, $forceUpdate, $stats);
        });
    }

    /**
     * Process channel data
     */
    private function processChannel(array $orderData, array &$stats): Channel
    {
        $channelName = $orderData['Source'] ?? 'unknown';
        $channelDisplayName = $orderData['Source'] ?? 'Unknown Channel';

        $channel = Channel::firstOrCreate(
            ['name' => $channelName],
            [
                'display_name' => $channelDisplayName,
                'type' => $this->determineChannelType($channelName),
                'currency' => $orderData['Currency'] ?? 'GBP',
                'is_active' => true,
            ]
        );

        if ($channel->wasRecentlyCreated) {
            $stats['channels_created']++;
        }

        return $channel;
    }

    /**
     * Process order items
     */
    private function processOrderItems(Order $order, array $orderData, bool $forceUpdate, array &$stats): void
    {
        // Clear existing items if force updating
        if ($forceUpdate) {
            $order->items()->delete();
        }

        $items = $orderData['Items'] ?? [];

        foreach ($items as $itemData) {
            $itemAttributes = [
                'order_id' => $order->id,
                'linnworks_item_id' => $itemData['pkOrderItemID'] ?? $itemData['ItemId'] ?? '',
                'sku' => $itemData['SKU'] ?? '',
                'title' => $itemData['ItemTitle'] ?? $itemData['Title'] ?? '',
                'description' => $itemData['ItemDescription'] ?? $itemData['Description'] ?? null,
                'category' => $itemData['Category'] ?? null,
                'quantity' => $itemData['nQty'] ?? $itemData['Quantity'] ?? 1,
                'unit_price' => $itemData['PricePerUnit'] ?? $itemData['UnitPrice'] ?? 0,
                'total_price' => $itemData['TotalPrice'] ?? ($itemData['PricePerUnit'] ?? 0) * ($itemData['nQty'] ?? 1),
                'cost_price' => $itemData['StockCostPrice'] ?? $itemData['CostPrice'] ?? null,
                'profit_margin' => $itemData['ProfitMargin'] ?? null,
                'tax_rate' => $itemData['TaxRate'] ?? 0,
                'discount_amount' => $itemData['DiscountAmount'] ?? 0,
                'bin_rack' => $itemData['BinRack'] ?? null,
                'is_service' => $itemData['IsService'] ?? false,
                'item_attributes' => $itemData,
            ];

            OrderItem::create($itemAttributes);
            $stats['items_processed']++;
        }
    }

    /**
     * Process address data
     */
    private function processAddresses(array $orderData): array
    {
        $addresses = [];

        // Billing address
        if (isset($orderData['BillingAddress'])) {
            $addresses['billing'] = $orderData['BillingAddress'];
        }

        // Shipping address
        if (isset($orderData['ShippingAddress'])) {
            $addresses['shipping'] = $orderData['ShippingAddress'];
        }

        return $addresses;
    }

    /**
     * Map Linnworks order status to our system status
     */
    private function mapOrderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'pending', 'new', 'unprocessed' => 'pending',
            'processed', 'dispatched', 'shipped' => 'processed',
            'cancelled', 'canceled' => 'cancelled',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Determine channel type based on channel name
     */
    private function determineChannelType(string $channelName): string
    {
        $channelName = strtolower($channelName);

        if (str_contains($channelName, 'amazon')) {
            return 'marketplace';
        }

        if (str_contains($channelName, 'ebay')) {
            return 'marketplace';
        }

        if (str_contains($channelName, 'website') || str_contains($channelName, 'web')) {
            return 'website';
        }

        return 'marketplace';
    }

    /**
     * Parse date string to Carbon instance
     */
    private function parseDate($date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', ['date' => $date, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
