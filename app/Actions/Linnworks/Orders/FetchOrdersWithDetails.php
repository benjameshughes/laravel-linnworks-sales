<?php

declare(strict_types=1);

namespace App\Actions\Linnworks\Orders;

use App\Services\Linnworks\Orders\OrdersApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FetchOrdersWithDetails
{
    public function __construct(
        private readonly OrdersApiService $orders,
    ) {}

    /**
     * Fetch paginated orders and hydrate each with the full detail payload.
     *
     * @return array<array<string,mixed>>
     */
    public function handle(
        int $userId,
        Carbon $from,
        Carbon $to,
        int $entriesPerPage = 200
    ): array {
        $pageNumber = 1;
        $allOrders = [];

        do {
            $response = $this->orders->getOrders($userId, $from, $to, $pageNumber, $entriesPerPage);

            if ($response->isError()) {
                Log::error('Failed fetching Linnworks order page.', [
                    'user_id' => $userId,
                    'page' => $pageNumber,
                    'error' => $response->error,
                ]);
                break;
            }

            $payload = $response->getData();
            $orders = collect($payload->get('Data', []));

            if ($orders->isEmpty()) {
                break;
            }

            $orderIds = $orders->pluck('pkOrderID')->filter()->values()->toArray();

            if (! empty($orderIds)) {
                $detailsResponse = $this->orders->getOrdersByIds($userId, $orderIds);

                if ($detailsResponse->isError()) {
                    Log::warning('Failed fetching Linnworks order details batch.', [
                        'user_id' => $userId,
                        'page' => $pageNumber,
                        'error' => $detailsResponse->error,
                    ]);
                } else {
                    $detailsResponse->getData()->each(function ($order) use (&$allOrders) {
                        $allOrders[] = is_array($order) ? $order : (array) $order;
                    });
                }
            }

            $pageNumber++;
        } while ($orders->count() === $entriesPerPage);

        return $allOrders;
    }
}
