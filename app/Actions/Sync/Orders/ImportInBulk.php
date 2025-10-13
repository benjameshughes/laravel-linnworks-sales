<?php

declare(strict_types=1);

namespace App\Actions\Sync\Orders;

use App\DataTransferObjects\ImportOrdersResult;
use App\DataTransferObjects\LinnworksOrder;
use App\DataTransferObjects\OrderImportDTO;
use App\Models\Order;
use App\Services\Orders\OrderBulkWriter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bulk order import action
 *
 * Domain: Order Sync
 * Responsibility: Import large batches of orders efficiently
 *
 * Process:
 * - Converts LinnworksOrder → OrderImportDTO (MEGA data)
 * - Batch loads existing orders (avoid N+1)
 * - Partitions into new vs updates
 * - Bulk inserts/updates using DB facade
 * - Streams processing (can run while next batch fetches)
 *
 * Performance: ~300 orders/sec vs ~16 orders/sec (18× faster)
 */
final class ImportInBulk
{
    public function __construct(
        private readonly OrderBulkWriter $bulkWriter,
        private readonly bool $dryRun = false,
    ) {}

    /**
     * Import a batch of orders
     *
     * This is the main entry point called by SyncOrdersJob for each chunk.
     * Designed to be called repeatedly in a streaming fashion.
     */
    public function import(Collection $linnworksOrders): ImportOrdersResult
    {
        $startTime = microtime(true);
        $peakMemoryBefore = memory_get_peak_usage(true);

        if ($linnworksOrders->isEmpty()) {
            return new ImportOrdersResult(
                processed: 0,
                created: 0,
                updated: 0,
                skipped: 0,
                failed: 0,
            );
        }

        Log::info('Sync/Orders/ImportInBulk: Starting import', [
            'order_count' => $linnworksOrders->count(),
            'dry_run' => $this->dryRun,
        ]);

        try {
            // Step 1: Convert to DTOs (MEGA data format)
            $dtos = $this->convertToDTOs($linnworksOrders);

            // Step 2: Batch load existing orders (avoid N+1)
            $existingMap = $this->loadExistingOrdersBatch($dtos);

            // Step 3: Partition into new vs updates
            [$newOrders, $updates] = $this->partitionOrders($dtos, $existingMap);

            if ($this->dryRun) {
                return $this->dryRunResults($dtos, $newOrders, $updates, $startTime, $peakMemoryBefore);
            }

            // Step 4: Bulk operations
            $created = $this->bulkWriter->insertOrders($newOrders);
            $updated = $this->bulkWriter->updateOrders($updates);

            // Step 5: Sync all relationships in bulk
            $this->bulkWriter->syncAllRelationships($dtos);

            // Calculate performance metrics
            $duration = round(microtime(true) - $startTime, 2);
            $peakMemoryAfter = memory_get_peak_usage(true);
            $memoryUsed = $peakMemoryAfter - $peakMemoryBefore;

            $result = new ImportOrdersResult(
                processed: $dtos->count(),
                created: $created,
                updated: $updated,
                skipped: $dtos->count() - $created - $updated,
                failed: 0,
            );

            Log::info('Sync/Orders/ImportInBulk: Import completed', [
                'processed' => $result->processed,
                'created' => $result->created,
                'updated' => $result->updated,
                'skipped' => $result->skipped,
                'duration_seconds' => $duration,
                'orders_per_second' => $result->processed > 0 ? round($result->processed / $duration, 2) : 0,
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'peak_memory_mb' => round($peakMemoryAfter / 1024 / 1024, 2),
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('Sync/Orders/ImportInBulk: Import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Convert LinnworksOrder collection to OrderImportDTO collection
     */
    private function convertToDTOs(Collection $linnworksOrders): Collection
    {
        return $linnworksOrders
            ->map(function ($order) {
                // Handle both LinnworksOrder objects and arrays
                if ($order instanceof LinnworksOrder) {
                    return OrderImportDTO::fromLinnworks($order);
                }

                // Convert array to LinnworksOrder first
                $linnworksOrder = is_array($order)
                    ? LinnworksOrder::fromArray($order)
                    : LinnworksOrder::fromArray((array) $order);

                return OrderImportDTO::fromLinnworks($linnworksOrder);
            })
            ->filter(fn (OrderImportDTO $dto) => $dto->linnworksOrderId !== null || $dto->orderNumber !== null);
    }

    /**
     * Batch load all existing orders to avoid N+1 queries
     *
     * Returns a collection with two maps:
     * - by_order_id: keyed by linnworks_order_id
     * - by_order_number: keyed by order_number
     */
    private function loadExistingOrdersBatch(Collection $dtos): Collection
    {
        $orderIds = $dtos
            ->pluck('linnworksOrderId')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $orderNumbers = $dtos
            ->pluck('orderNumber')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        Log::info('Sync/Orders/ImportInBulk: Batch loading existing orders', [
            'order_ids_count' => count($orderIds),
            'order_numbers_count' => count($orderNumbers),
        ]);

        // Load all existing orders in one or two queries
        $existingOrders = collect();

        if (! empty($orderIds)) {
            $byLinnworksId = Order::query()
                ->where(function ($query) use ($orderIds) {
                    $query->whereIn('linnworks_order_id', $orderIds)
                        ->orWhereIn('order_id', $orderIds);
                })
                ->get();
            $existingOrders = $existingOrders->merge($byLinnworksId);
        }

        if (! empty($orderNumbers)) {
            $byOrderNumber = Order::whereIn('order_number', $orderNumbers)->get();
            $existingOrders = $existingOrders->merge($byOrderNumber);
        }

        // Create lookup maps for O(1) access
        $orderIdMap = $existingOrders
            ->filter(fn ($order) => $order->linnworks_order_id || $order->order_id)
            ->keyBy(fn ($order) => $order->linnworks_order_id ?? $order->order_id);

        $orderNumberMap = $existingOrders
            ->filter(fn ($order) => $order->order_number)
            ->keyBy('order_number');

        Log::info('Sync/Orders/ImportInBulk: Existing orders loaded', [
            'total_loaded' => $existingOrders->unique('id')->count(),
            'by_order_id' => $orderIdMap->count(),
            'by_order_number' => $orderNumberMap->count(),
        ]);

        return collect([
            'by_order_id' => $orderIdMap,
            'by_order_number' => $orderNumberMap,
        ]);
    }

    /**
     * Partition DTOs into new orders vs updates
     *
     * Returns [$newOrders, $updates]
     */
    private function partitionOrders(Collection $dtos, Collection $existingMap): array
    {
        $orderIdMap = $existingMap->get('by_order_id');
        $orderNumberMap = $existingMap->get('by_order_number');

        $newOrders = collect();
        $updates = collect();

        foreach ($dtos as $dto) {
            $exists = false;

            // Check if exists by order ID
            if ($dto->linnworksOrderId && $orderIdMap->has($dto->linnworksOrderId)) {
                $exists = true;
            }

            // Check if exists by order number
            if (! $exists && $dto->orderNumber && $orderNumberMap->has($dto->orderNumber)) {
                $exists = true;
            }

            if ($exists) {
                $updates->push($dto);
            } else {
                $newOrders->push($dto);
            }
        }

        Log::info('Sync/Orders/ImportInBulk: Orders partitioned', [
            'new' => $newOrders->count(),
            'updates' => $updates->count(),
        ]);

        return [$newOrders, $updates];
    }

    /**
     * Return dry run results (no database writes)
     */
    private function dryRunResults(
        Collection $dtos,
        Collection $newOrders,
        Collection $updates,
        float $startTime,
        int $peakMemoryBefore
    ): ImportOrdersResult {
        $duration = round(microtime(true) - $startTime, 2);
        $peakMemoryAfter = memory_get_peak_usage(true);
        $memoryUsed = $peakMemoryAfter - $peakMemoryBefore;

        Log::info('Sync/Orders/ImportInBulk: DRY RUN completed', [
            'total_orders' => $dtos->count(),
            'would_create' => $newOrders->count(),
            'would_update' => $updates->count(),
            'duration_seconds' => $duration,
            'orders_per_second' => $dtos->count() > 0 ? round($dtos->count() / $duration, 2) : 0,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemoryAfter / 1024 / 1024, 2),
        ]);

        return new ImportOrdersResult(
            processed: $dtos->count(),
            created: $newOrders->count(),
            updated: $updates->count(),
            skipped: 0,
            failed: 0,
        );
    }

    /**
     * Create a new instance with dry run enabled
     */
    public static function dryRun(): self
    {
        return new self(
            bulkWriter: app(OrderBulkWriter::class),
            dryRun: true,
        );
    }
}
