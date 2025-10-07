<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Orders;

use App\Models\LinnworksLocation;
use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class LocationsService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    public function getLocations(int $userId): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (!$sessionToken) {
            Log::error('No valid session token for locations request', ['user_id' => $userId]);
            return collect();
        }

        $request = ApiRequest::get('Inventory/GetStockLocations');
        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::warning('Failed to fetch Linnworks locations', [
                'user_id' => $userId,
                'error' => $response->error,
                'status' => $response->statusCode,
            ]);

            return LinnworksLocation::forUser($userId)
                ->get()
                ->map(fn (LinnworksLocation $location) => $location->metadata ?? [
                    'LocationId' => $location->location_id,
                    'Name' => $location->name,
                ]);
        }

        $locations = $response->getData()->map(fn ($location) => is_array($location) ? $location : (array) $location);

        $locations->each(function (array $location) use ($userId) {
            $locationId = $location['StockLocationId']
                ?? $location['LocationId']
                ?? $location['Id']
                ?? null;

            if (!$locationId) {
                return;
            }

            LinnworksLocation::updateOrCreate(
                ['user_id' => $userId, 'location_id' => (string) $locationId],
                [
                    'name' => $location['LocationName'] ?? $location['Name'] ?? 'Unnamed Location',
                    'is_default' => (bool) ($location['IsDefault'] ?? $location['IsDefaultLocation'] ?? false),
                    'metadata' => $location,
                ]
            );
        });

        return $locations;
    }

    public function getDefaultLocation(int $userId): ?array
    {
        $default = LinnworksLocation::forUser($userId)
            ->where('is_default', true)
            ->first();

        if ($default) {
            return $default->metadata ?? [
                'LocationId' => $default->location_id,
                'Name' => $default->name,
            ];
        }

        $first = LinnworksLocation::forUser($userId)->first();

        if ($first) {
            return $first->metadata ?? [
                'LocationId' => $first->location_id,
                'Name' => $first->name,
            ];
        }

        $locations = $this->getLocations($userId);

        return $locations->first();
    }
}
