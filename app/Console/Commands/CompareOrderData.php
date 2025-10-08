<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Linnworks\Orders\OpenOrdersService;
use Illuminate\Console\Command;

class CompareOrderData extends Command
{
    protected $signature = 'debug:compare-order {order_id?}';

    protected $description = 'Compare database order data with Linnworks API data';

    public function handle(OpenOrdersService $openOrdersService): int
    {
        // Get order ID from argument or find a recent one
        $orderId = $this->argument('order_id');

        if (!$orderId) {
            $order = Order::where('received_date', '>=', now()->subDays(7))
                ->where('channel_name', '!=', 'DIRECT')
                ->first();

            if (!$order) {
                $this->error('No recent orders found');
                return self::FAILURE;
            }

            $orderId = $order->order_id;
            $this->info("Using recent order: {$orderId}");
        }

        // Get from database
        $dbOrder = Order::where('order_id', $orderId)->first();

        if (!$dbOrder) {
            $this->error("Order {$orderId} not found in database");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('📋 DATABASE ORDER:');
        $this->table(
            ['Field', 'Value'],
            [
                ['UUID', $dbOrder->order_id],
                ['Order Number', $dbOrder->order_number],
                ['Channel', $dbOrder->channel_name],
                ['Status', $dbOrder->status],
                ['is_open', $dbOrder->is_open ? 'YES' : 'NO'],
                ['is_processed', $dbOrder->is_processed ? 'YES' : 'NO'],
                ['Received', $dbOrder->received_date],
                ['Processed', $dbOrder->processed_date ?? 'NULL'],
                ['total_charge', '£' . number_format((float)$dbOrder->total_charge, 2)],
                ['total_paid', '£' . number_format((float)$dbOrder->total_paid, 2)],
                ['postage_cost', '£' . number_format((float)$dbOrder->postage_cost, 2)],
                ['tax', '£' . number_format((float)$dbOrder->tax, 2)],
                ['Items count', count($dbOrder->items ?? [])],
            ]
        );

        if (!empty($dbOrder->items)) {
            $this->newLine();
            $this->info('DB Items:');
            $this->table(
                ['SKU', 'Quantity', 'Price/Unit', 'Line Total'],
                collect($dbOrder->items)->map(fn($item) => [
                    $item['sku'] ?? 'N/A',
                    $item['quantity'] ?? 0,
                    '£' . number_format($item['price_per_unit'] ?? 0, 2),
                    '£' . number_format($item['line_total'] ?? 0, 2),
                ])->toArray()
            );
        }

        // Get from Linnworks API
        $this->newLine();
        $this->info('🌐 Fetching from Linnworks API...');

        try {
            $apiResponse = $openOrdersService->getOpenOrderDetails(userId: 1, orderId: $orderId);

            if ($apiResponse->isError()) {
                $this->error('API Error: ' . $apiResponse->error);
                return self::FAILURE;
            }

            $apiData = $apiResponse->getData()->toArray();
            $general = $apiData['GeneralInfo'] ?? [];
            $totals = $apiData['TotalsInfo'] ?? [];
            $items = $apiData['Items'] ?? [];

            $this->newLine();
            $this->info('📦 LINNWORKS API ORDER:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OrderId', $apiData['OrderId'] ?? 'N/A'],
                    ['NumOrderId', $apiData['NumOrderId'] ?? 'N/A'],
                    ['Source', $general['Source'] ?? 'N/A'],
                    ['Status', $general['Status'] ?? 'N/A'],
                    ['ReceivedDate', $general['ReceivedDate'] ?? 'N/A'],
                    ['TotalCharge', '£' . number_format($totals['TotalCharge'] ?? 0, 2)],
                    ['PostageCost', '£' . number_format($totals['PostageCost'] ?? 0, 2)],
                    ['Tax', '£' . number_format($totals['Tax'] ?? 0, 2)],
                    ['Subtotal', '£' . number_format($totals['Subtotal'] ?? 0, 2)],
                    ['ProfitMargin', '£' . number_format($totals['ProfitMargin'] ?? 0, 2)],
                    ['Items count', count($items)],
                ]
            );

            if (!empty($items)) {
                $this->newLine();
                $this->info('API Items:');
                $this->table(
                    ['SKU', 'Title', 'Quantity', 'Price/Unit', 'Line Total', 'Line Total Ex Tax'],
                    collect($items)->map(fn($item) => [
                        $item['SKU'] ?? 'N/A',
                        $this->truncate($item['ItemTitle'] ?? 'N/A', 30),
                        $item['Quantity'] ?? 0,
                        '£' . number_format($item['PricePerUnit'] ?? 0, 2),
                        '£' . number_format($item['LineTotal'] ?? 0, 2),
                        '£' . number_format($item['LineTotalExTax'] ?? 0, 2),
                    ])->toArray()
                );
            }

            // Comparison
            $this->newLine();
            $this->info('🔍 COMPARISON:');

            $dbTotal = (float)$dbOrder->total_charge;
            $apiTotal = (float)($totals['TotalCharge'] ?? 0);
            $dbItemTotal = (float)($dbOrder->items[0]['line_total'] ?? 0);
            $apiItemTotal = (float)($items[0]['LineTotal'] ?? 0);

            $this->table(
                ['Field', 'Database', 'API', 'Match?'],
                [
                    ['Total Charge', '£' . number_format($dbTotal, 2), '£' . number_format($apiTotal, 2), $dbTotal == $apiTotal ? '✓' : '✗'],
                    ['Item Line Total', '£' . number_format($dbItemTotal, 2), '£' . number_format($apiItemTotal, 2), $dbItemTotal == $apiItemTotal ? '✓' : '✗'],
                    ['Items Count', count($dbOrder->items ?? []), count($items), count($dbOrder->items ?? []) == count($items) ? '✓' : '✗'],
                ]
            );

            // Debug raw API response
            $this->newLine();
            $this->info('🔍 RAW API RESPONSE (first 500 chars):');
            $this->line(substr(json_encode($apiData, JSON_PRETTY_PRINT), 0, 500));

            if ($dbTotal != $apiTotal) {
                $this->newLine();
                $this->warn('⚠️  MISMATCH DETECTED: Database total_charge does not match API TotalCharge!');
                $this->warn('This indicates the sync is not capturing revenue data correctly.');
            }

        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
