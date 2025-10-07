<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Orders;

use App\DataTransferObjects\ImportOrdersResult;
use App\Events\SyncCompleted;
use App\Events\SyncProgressUpdated;
use App\Events\SyncStarted;
use App\Services\LinnworksApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class SyncRecentOrders
{
    public function __construct(
        private readonly LinnworksApiService $api,
        private readonly ImportOrders $importOrders,
    ) {}

    public function handle(
        int $openWindowDays = 7,
        int $processedWindowDays = 30,
        bool $forceUpdate = false,
        ?int $userId = null
    ): ImportOrdersResult {
        $openWindowDays = max(1, $openWindowDays);
        $processedWindowDays = max($openWindowDays, $processedWindowDays);

        Log::info('SyncRecentOrders: starting sync', [
            'user_id' => $userId,
            'open_window_days' => $openWindowDays,
            'processed_window_days' => $processedWindowDays,
            'force_update' => $forceUpdate,
        ]);

        // Broadcast sync started
        event(new SyncStarted($openWindowDays, $processedWindowDays));

        // Broadcast progress - fetching open orders
        event(new SyncProgressUpdated('fetching-open', 'Fetching open orders...'));

        $openOrders = $this->api->getRecentOpenOrders(
            userId: $userId,
            days: $openWindowDays,
            maxOrders: (int) config('linnworks.sync.max_open_orders', 1000),
        );

        Log::info('SyncRecentOrders: open orders fetched', [
            'user_id' => $userId,
            'open_orders_count' => $openOrders->count(),
            'open_window_days' => $openWindowDays,
        ]);

        // Broadcast progress - open orders fetched
        event(new SyncProgressUpdated('fetched-open', "Fetched {$openOrders->count()} open orders", $openOrders->count()));

        // Broadcast progress - fetching processed orders
        event(new SyncProgressUpdated('fetching-processed', 'Fetching processed orders...'));

        $from = Carbon::now()->subDays($processedWindowDays)->startOfDay();
        $to = Carbon::now()->endOfDay();

        $processedOrders = $this->api->getAllProcessedOrders(
            from: $from,
            to: $to,
            filters: [],
            maxOrders: (int) config('linnworks.sync.max_processed_orders', 5000),
            userId: $userId,
        );

        Log::info('SyncRecentOrders: processed orders fetched', [
            'user_id' => $userId,
            'processed_orders_count' => $processedOrders->count(),
            'from' => $from->toISOString(),
            'to' => $to->toISOString(),
        ]);

        // Broadcast progress - processed orders fetched
        event(new SyncProgressUpdated('fetched-processed', "Fetched {$processedOrders->count()} processed orders", $processedOrders->count()));

        $combinedOrders = $openOrders->concat($processedOrders)->values();

        Log::info('SyncRecentOrders: importing combined orders', [
            'user_id' => $userId,
            'combined_count' => $combinedOrders->count(),
            'force_update' => $forceUpdate,
        ]);

        // Broadcast progress - importing
        event(new SyncProgressUpdated('importing', "Importing {$combinedOrders->count()} orders...", $combinedOrders->count()));

        $result = $this->importOrders->handle($combinedOrders, $forceUpdate);

        Log::info('SyncRecentOrders: import finished', [
            'user_id' => $userId,
            ...$result->toArray(),
        ]);

        // Broadcast sync completed
        event(new SyncCompleted(
            processed: $result->processed,
            created: $result->created,
            updated: $result->updated,
            failed: $result->failed,
            success: $result->failed === 0,
        ));

        return $result;
    }
}
