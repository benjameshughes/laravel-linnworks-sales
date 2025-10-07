<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Orders;

use App\Actions\Orders\MarkOrderAsProcessed;
use App\Services\Linnworks\Orders\OrdersApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CheckAndUpdateProcessedOrders
{
    public function __construct(
        private readonly OrdersApiService $orders,
        private readonly MarkOrderAsProcessed $markOrderAsProcessed,
    ) {}

    public function handle(int $userId, Collection $orderIds, int $batchSize = 50): bool
    {
        if ($orderIds->isEmpty()) {
            return true;
        }

        $processedData = collect();

        foreach ($orderIds->chunk($batchSize) as $chunk) {
            $response = $this->orders->getOrdersByIds($userId, $chunk->toArray());

            if ($response->isError()) {
                Log::warning('Failed to inspect processed status for Linnworks order batch.', [
                    'error' => $response->error,
                ]);
                continue;
            }

            $response->getData()->each(function ($order) use (&$processedData) {
                $payload = is_array($order) ? $order : (array) $order;

                $processedData->push([
                    'order_id' => $payload['OrderId'] ?? $payload['pkOrderID'] ?? null,
                    'is_processed' => (bool) ($payload['Processed'] ?? false),
                ]);
            });

            usleep(200_000);
        }

        if ($processedData->isEmpty()) {
            return true;
        }

        $result = $this->markOrderAsProcessed->handle($processedData);

        Log::info('Processed orders status update completed.', [
            'checked' => $processedData->count(),
            'result' => $result,
        ]);

        return $result;
    }
}
