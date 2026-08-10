<?php

declare(strict_types=1);

namespace App\Actions\Linnworks;

use App\Models\LinnworksView;
use App\Models\LinnworksLocation;
use BenHughes\Linnworks\LinnworksClient;

/**
 * Mirror the Linnworks stock locations and order views into local tables.
 *
 * The settings screen reads these from the database so it can render without
 * an API round trip; this refills them when they are empty.
 */
final readonly class CacheLocationsAndViews
{
    public function __construct(
        private LinnworksClient $linnworks,
    ) {}

    public function __invoke(int $userId): void
    {
        $this->cacheLocations($userId);
        $this->cacheViews($userId);
    }

    public function cacheLocations(int $userId): void
    {
        $this->linnworks->locations()->all()
            ->map(fn ($location): array => (array) $location)
            ->each(function (array $location) use ($userId): void {
                $id = $location['StockLocationId'] ?? $location['LocationId'] ?? $location['Id'] ?? null;

                if (! $id) {
                    return;
                }

                LinnworksLocation::updateOrCreate(
                    ['user_id' => $userId, 'location_id' => (string) $id],
                    [
                        'name' => $location['LocationName'] ?? $location['Name'] ?? 'Unnamed Location',
                        'is_default' => (bool) ($location['IsDefault'] ?? $location['IsDefaultLocation'] ?? false),
                        'metadata' => $location,
                    ],
                );
            });
    }

    public function cacheViews(int $userId): void
    {
        $this->linnworks->orders()->views()
            ->map(fn ($view): array => (array) $view)
            ->each(function (array $view) use ($userId): void {
                $id = $view['ViewId'] ?? $view['Id'] ?? null;

                if (! $id) {
                    return;
                }

                LinnworksView::updateOrCreate(
                    ['user_id' => $userId, 'view_id' => (string) $id],
                    [
                        'name' => $view['ViewName'] ?? $view['Name'] ?? 'Unnamed View',
                        'metadata' => $view,
                    ],
                );
            });
    }
}
