<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\DataTransferObjects\OrderImportDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bulk database operations for order imports using DB facade
 *
 * NO ELOQUENT - Pure DB::table() operations for maximum performance.
 * All methods use bulk inserts/updates to minimize database round-trips.
 *
 * Performance pattern: Collect → Delete → Insert (all in bulk)
 */
final class OrderBulkWriter
{
    /**
     * Bulk insert new orders
     *
     * Single INSERT statement for all new orders.
     * ~200× faster than N individual inserts.
     */
    public function insertOrders(Collection $dtos): int
    {
        if ($dtos->isEmpty()) {
            return 0;
        }

        $rows = $dtos->map(fn (OrderImportDTO $dto) => $dto->order)->toArray();

        // Single bulk insert
        DB::table('orders')->insert($rows);

        Log::info('OrderBulkWriter: Bulk inserted orders', [
            'count' => count($rows),
        ]);

        return count($rows);
    }

    /**
     * Bulk update existing orders
     *
     * Updates in chunks to avoid massive single queries.
     * Uses WHERE linnworks_order_id for fast lookups.
     */
    public function updateOrders(Collection $dtos): int
    {
        if ($dtos->isEmpty()) {
            return 0;
        }

        $updated = 0;

        // Chunk to avoid massive queries (50 updates per transaction)
        $dtos->chunk(50)->each(function (Collection $chunk) use (&$updated) {
            DB::transaction(function () use ($chunk, &$updated) {
                foreach ($chunk as $dto) {
                    $affected = DB::table('orders')
                        ->where('linnworks_order_id', $dto->linnworksOrderId)
                        ->update($dto->order);

                    $updated += $affected;
                }
            });
        });

        Log::info('OrderBulkWriter: Bulk updated orders', [
            'count' => $updated,
        ]);

        return $updated;
    }

    /**
     * Bulk sync order items for all orders
     *
     * Pattern: Collect all order IDs → DELETE all → INSERT all
     * Result: N delete + N insert → 1 delete + 1 insert
     *
     * IMPORTANT: Skips items without SKUs (unlinked marketplace items)
     * and ensures products exist before creating order items.
     */
    public function syncItems(Collection $dtos): void
    {
        if ($dtos->isEmpty()) {
            return;
        }

        // Collect ALL linnworks_order_ids to get DB IDs
        $linnworksOrderIds = $dtos->pluck('linnworksOrderId')->unique()->toArray();

        // Get actual database order IDs
        $dbOrderIds = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id')
            ->toArray();

        // Single DELETE for all orders (using order_id foreign key)
        $deleted = DB::table('order_items')
            ->whereIn('order_id', $dbOrderIds)
            ->delete();

        // Get actual database order IDs for setting foreign keys
        $dbOrderMap = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id', 'linnworks_order_id')
            ->toArray();

        // Collect all SKUs for product existence check
        $allSkus = $dtos->flatMap(fn (OrderImportDTO $dto) => collect($dto->items)->pluck('sku')->filter())
            ->unique()
            ->values()
            ->toArray();

        // Batch check which products exist
        $existingSkus = DB::table('products')
            ->whereIn('sku', $allSkus)
            ->pluck('sku')
            ->toArray();

        $missingSkus = array_diff($allSkus, $existingSkus);

        // Create missing products in bulk
        if (! empty($missingSkus)) {
            $productsToCreate = [];
            foreach ($missingSkus as $sku) {
                $productsToCreate[] = [
                    'sku' => $sku,
                    'linnworks_id' => 'UNKNOWN_'.strtoupper($sku), // Make linnworks_id unique
                    'title' => 'Unknown Product',
                    'stock_level' => 0,
                    'is_active' => true,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ];
            }

            DB::table('products')->insert($productsToCreate);

            Log::info('OrderBulkWriter: Created missing products', [
                'count' => count($productsToCreate),
            ]);
        }

        // Collect ALL items from ALL orders and set order_id foreign key
        // Skip items without SKUs (unlinked marketplace items)
        $skippedCount = 0;
        $allItems = $dtos->flatMap(function (OrderImportDTO $dto) use ($dbOrderMap, &$skippedCount) {
            $orderId = $dbOrderMap[$dto->linnworksOrderId] ?? null;

            if (! $orderId) {
                Log::warning('OrderBulkWriter: Order not found for items', [
                    'linnworks_order_id' => $dto->linnworksOrderId,
                ]);

                return [];
            }

            return collect($dto->items)->filter(function (array $item) use (&$skippedCount) {
                // Skip items without SKUs - these are unlinked marketplace items
                if (empty($item['sku'])) {
                    $skippedCount++;

                    return false;
                }

                return true;
            })->map(function (array $item) use ($orderId) {
                $item['order_id'] = $orderId;

                return $item;
            });
        })->toArray();

        if (empty($allItems)) {
            Log::info('OrderBulkWriter: No items to sync', [
                'skipped_no_sku' => $skippedCount,
            ]);

            return;
        }

        // Single INSERT for all items
        DB::table('order_items')->insert($allItems);

        Log::info('OrderBulkWriter: Bulk synced order items', [
            'orders_count' => count($linnworksOrderIds),
            'deleted' => $deleted,
            'inserted' => count($allItems),
            'skipped_no_sku' => $skippedCount,
            'products_created' => count($missingSkus),
        ]);
    }

    /**
     * Bulk sync shipping info for all orders
     */
    public function syncShipping(Collection $dtos): void
    {
        // Only process DTOs that have shipping data
        $dtosWithShipping = $dtos->filter(fn (OrderImportDTO $dto) => $dto->shipping !== null);

        if ($dtosWithShipping->isEmpty()) {
            return;
        }

        $linnworksOrderIds = $dtosWithShipping->pluck('linnworksOrderId')->unique()->toArray();

        // Get DB order IDs
        $dbOrderIds = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id')
            ->toArray();

        // Single DELETE for all (using order_id foreign key)
        $deleted = DB::table('order_shipping')
            ->whereIn('order_id', $dbOrderIds)
            ->delete();

        // Get actual database order IDs
        $dbOrderMap = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id', 'linnworks_order_id')
            ->toArray();

        // Collect all shipping records and set order_id
        $allShipping = $dtosWithShipping->map(function (OrderImportDTO $dto) use ($dbOrderMap) {
            $orderId = $dbOrderMap[$dto->linnworksOrderId] ?? null;

            if (! $orderId) {
                return null;
            }

            $shipping = $dto->shipping;
            $shipping['order_id'] = $orderId;

            return $shipping;
        })->filter()->toArray();

        if (empty($allShipping)) {
            return;
        }

        // Single INSERT for all shipping records
        DB::table('order_shipping')->insert($allShipping);

        Log::info('OrderBulkWriter: Bulk synced shipping', [
            'orders_count' => count($linnworksOrderIds),
            'deleted' => $deleted,
            'inserted' => count($allShipping),
        ]);
    }

    /**
     * Bulk sync order notes for all orders
     */
    public function syncNotes(Collection $dtos): void
    {
        // Only process DTOs that have notes
        $dtosWithNotes = $dtos->filter(fn (OrderImportDTO $dto) => ! empty($dto->notes));

        if ($dtosWithNotes->isEmpty()) {
            return;
        }

        $linnworksOrderIds = $dtosWithNotes->pluck('linnworksOrderId')->unique()->toArray();

        // Get DB order IDs
        $dbOrderIds = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id')
            ->toArray();

        // Single DELETE for all (using order_id foreign key)
        $deleted = DB::table('order_notes')
            ->whereIn('order_id', $dbOrderIds)
            ->delete();

        // Get actual database order IDs for setting foreign keys
        $dbOrderMap = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id', 'linnworks_order_id')
            ->toArray();

        // Collect ALL notes from ALL orders
        $allNotes = $dtosWithNotes->flatMap(function (OrderImportDTO $dto) use ($dbOrderMap) {
            $orderId = $dbOrderMap[$dto->linnworksOrderId] ?? null;

            if (! $orderId) {
                return [];
            }

            return collect($dto->notes)->map(function (array $note) use ($orderId) {
                $note['order_id'] = $orderId;

                return $note;
            });
        })->toArray();

        if (empty($allNotes)) {
            return;
        }

        // Single INSERT for all notes
        DB::table('order_notes')->insert($allNotes);

        Log::info('OrderBulkWriter: Bulk synced notes', [
            'orders_count' => count($linnworksOrderIds),
            'deleted' => $deleted,
            'inserted' => count($allNotes),
        ]);
    }

    /**
     * Bulk sync order properties for all orders
     */
    public function syncProperties(Collection $dtos): void
    {
        $dtosWithProperties = $dtos->filter(fn (OrderImportDTO $dto) => ! empty($dto->properties));

        if ($dtosWithProperties->isEmpty()) {
            return;
        }

        $linnworksOrderIds = $dtosWithProperties->pluck('linnworksOrderId')->unique()->toArray();

        // Get DB order IDs
        $dbOrderIds = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id')
            ->toArray();

        // Single DELETE for all (using order_id foreign key)
        $deleted = DB::table('order_properties')
            ->whereIn('order_id', $dbOrderIds)
            ->delete();

        // Get actual database order IDs for setting foreign keys
        $dbOrderMap = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id', 'linnworks_order_id')
            ->toArray();

        $allProperties = $dtosWithProperties->flatMap(function (OrderImportDTO $dto) use ($dbOrderMap) {
            $orderId = $dbOrderMap[$dto->linnworksOrderId] ?? null;

            if (! $orderId) {
                return [];
            }

            return collect($dto->properties)->map(function (array $property) use ($orderId) {
                $property['order_id'] = $orderId;

                return $property;
            });
        })->toArray();

        if (empty($allProperties)) {
            return;
        }

        DB::table('order_properties')->insert($allProperties);

        Log::info('OrderBulkWriter: Bulk synced properties', [
            'orders_count' => count($linnworksOrderIds),
            'deleted' => $deleted,
            'inserted' => count($allProperties),
        ]);
    }

    /**
     * Bulk sync order identifiers for all orders
     */
    public function syncIdentifiers(Collection $dtos): void
    {
        $dtosWithIdentifiers = $dtos->filter(fn (OrderImportDTO $dto) => ! empty($dto->identifiers));

        if ($dtosWithIdentifiers->isEmpty()) {
            return;
        }

        $linnworksOrderIds = $dtosWithIdentifiers->pluck('linnworksOrderId')->unique()->toArray();

        // Get DB order IDs
        $dbOrderIds = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id')
            ->toArray();

        // Single DELETE for all (using order_id foreign key)
        $deleted = DB::table('order_identifiers')
            ->whereIn('order_id', $dbOrderIds)
            ->delete();

        // Get actual database order IDs for setting foreign keys
        $dbOrderMap = DB::table('orders')
            ->whereIn('linnworks_order_id', $linnworksOrderIds)
            ->pluck('id', 'linnworks_order_id')
            ->toArray();

        $allIdentifiers = $dtosWithIdentifiers->flatMap(function (OrderImportDTO $dto) use ($dbOrderMap) {
            $orderId = $dbOrderMap[$dto->linnworksOrderId] ?? null;

            if (! $orderId) {
                return [];
            }

            return collect($dto->identifiers)->map(function (array $identifier) use ($orderId) {
                $identifier['order_id'] = $orderId;

                return $identifier;
            });
        })->toArray();

        if (empty($allIdentifiers)) {
            return;
        }

        DB::table('order_identifiers')->insert($allIdentifiers);

        Log::info('OrderBulkWriter: Bulk synced identifiers', [
            'orders_count' => count($linnworksOrderIds),
            'deleted' => $deleted,
            'inserted' => count($allIdentifiers),
        ]);
    }

    /**
     * Sync all relationships in one go
     *
     * Calls all sync methods in sequence.
     * This is the main entry point for relationship syncing.
     */
    public function syncAllRelationships(Collection $dtos): void
    {
        $this->syncItems($dtos);
        $this->syncShipping($dtos);
        $this->syncNotes($dtos);
        $this->syncProperties($dtos);
        $this->syncIdentifiers($dtos);
    }
}
