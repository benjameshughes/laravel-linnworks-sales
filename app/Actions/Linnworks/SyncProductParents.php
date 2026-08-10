<?php

declare(strict_types=1);

namespace App\Actions\Linnworks;

use App\Models\Product;
use App\Models\ProductParent;
use Illuminate\Support\Collection;
use BenHughes\Linnworks\LinnworksClient;

/**
 * Mirror Linnworks variation groups into product_parents and point each child
 * product at the group it belongs to.
 *
 * A variation group is not a stock item, so it gets its own table rather than
 * living on products. Children are linked by foreign key, which is what makes
 * order_items.parent_sku redundant.
 *
 * @phpstan-type SyncResult array{groups: int, linked: int, unmatched: int}
 */
final readonly class SyncProductParents
{
    public function __construct(
        private LinnworksClient $linnworks,
    ) {}

    /**
     * @param  bool  $onlyUnlinked  Skip products that already have a parent. Cheap
     *                              and self-healing, but blind to a product that
     *                              has moved between groups in Linnworks.
     * @return SyncResult
     */
    public function __invoke(bool $onlyUnlinked = true): array
    {
        $groups = $this->groups();
        $linked = 0;
        $unmatched = 0;

        $groups->each(function (array $group) use ($onlyUnlinked, &$linked, &$unmatched): void {
            $parent = ProductParent::updateOrCreate(
                ['linnworks_id' => (string) $group['pkVariationItemId']],
                [
                    'sku' => (string) $group['VariationSKU'],
                    'title' => $group['VariationGroupName'] ?? null,
                    'metadata' => $group,
                    'last_synced_at' => now(),
                ],
            );

            $childIds = $this->childStockItemIds($parent->linnworks_id);

            if ($childIds->isEmpty()) {
                return;
            }

            $known = Product::query()->whereIn('linnworks_id', $childIds->all());

            $matched = (clone $known)
                ->when($onlyUnlinked, fn ($query) => $query->whereNull('product_parent_id'))
                ->update(['product_parent_id' => $parent->id]);

            $linked += $matched;
            $unmatched += $childIds->count() - $known->count();
        });

        return [
            'groups' => $groups->count(),
            'linked' => $linked,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * Walk every page of variation groups rather than trusting the first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function groups(): Collection
    {
        $groups = collect();
        $page = 1;

        do {
            $response = $this->linnworks->stock()->searchVariationGroups(
                searchType: 'ParentSKU',
                page: $page,
                pageSize: 200,
            );

            $groups = $groups->concat($response['Data'] ?? []);
            $totalPages = (int) ($response['TotalPages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $groups
            ->filter(fn (array $group): bool => ! empty($group['pkVariationItemId']) && ! empty($group['VariationSKU']))
            ->reject(fn (array $group): bool => $this->isExcluded((string) $group['VariationSKU']))
            ->values();
    }

    /**
     * Duplicate groups left behind in Linnworks would attribute the same sale
     * to two parents, so they are skipped rather than stored.
     */
    private function isExcluded(string $sku): bool
    {
        $pattern = config('linnworks.variation_groups.exclude_pattern');

        return $pattern !== null && preg_match($pattern, $sku) === 1;
    }

    /**
     * Children are matched on Linnworks stock item id, never on SKU text.
     *
     * @return Collection<int, string>
     */
    private function childStockItemIds(string $variationId): Collection
    {
        return $this->linnworks->stock()->variationItems($variationId)
            ->map(fn ($item): ?string => data_get($item, 'pkStockItemId'))
            ->filter()
            ->map(fn (string $id): string => $id)
            ->values();
    }
}
