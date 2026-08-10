<?php

declare(strict_types=1);

namespace App\Actions\Linnworks;

use Illuminate\Support\Collection;
use BenHughes\Linnworks\Models\Order;
use BenHughes\Linnworks\LinnworksClient;
use App\DataTransferObjects\LinnworksOrder;

/**
 * Fetch full order detail for a set of Linnworks order ids.
 *
 * The only translation point between the package's order model and the DTO the
 * import pipeline expects. Failures bubble - an empty collection here would be
 * indistinguishable from "these orders do not exist" and would silently shrink
 * an import.
 */
final readonly class FetchOrderDetails
{
    public function __construct(
        private LinnworksClient $linnworks,
    ) {}

    /**
     * @param  array<int, string>  $orderIds
     * @return Collection<int, LinnworksOrder>
     */
    public function __invoke(array $orderIds): Collection
    {
        if ($orderIds === []) {
            return collect();
        }

        // Collection::chunk() preserves keys, so a later chunk arrives keyed
        // 200..399 and json_encode turns it into an object rather than an
        // array. Linnworks answers that with "Invalid parameter pkOrderIds".
        return $this->linnworks->orders()
            ->find(array_values($orderIds))
            ->map(fn (Order $order): LinnworksOrder => LinnworksOrder::fromArray($order->toArray()));
    }
}
