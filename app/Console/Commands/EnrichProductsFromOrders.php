<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Laravel\Scout\ModelObserver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class EnrichProductsFromOrders extends Command
{
    private const CHUNK_SIZE = 100;

    private const PRICE_PRECISION = 4;

    protected $signature = 'products:enrich
                            {--force : Overwrite existing pricing data}';

    protected $description = 'Enrich product data from order item history (pricing, categories)';

    public function handle(): int
    {
        $this->info('Enriching products from order item data...');

        ModelObserver::disableSyncingFor(Product::class);

        $forceUpdate = (bool) $this->option('force');

        $aggregated = DB::table('order_items')
            ->select([
                'sku',
                DB::raw('AVG(price_per_unit) as avg_selling_price'),
                DB::raw('AVG(CASE WHEN unit_cost > 0 THEN unit_cost ELSE NULL END) as avg_unit_cost'),
                DB::raw('MAX(category_name) as category_name'),
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('COUNT(DISTINCT order_id) as order_count'),
            ])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->get();

        $updated = 0;
        $skipped = 0;

        $aggregated->chunk(self::CHUNK_SIZE)->each(function (Collection $chunk) use ($forceUpdate, &$updated, &$skipped): void {
            $products = Product::query()
                ->whereIn('sku', $chunk->pluck('sku'))
                ->get()
                ->keyBy('sku');

            $pending = $chunk
                ->map(fn (object $row): ?array => $this->resolveUpdate($row, $products->get($row->sku), $forceUpdate))
                ->filter();

            DB::transaction(fn () => $pending->each(
                fn (array $update): bool => $update['product']->update($update['changes'])
            ));

            $updated += $pending->count();
            $skipped += $chunk->count() - $pending->count();
        });

        ModelObserver::enableSyncingFor(Product::class);

        $this->info("Done. Updated: {$updated}, Skipped: {$skipped}");
        Log::info('Product enrichment from orders completed', compact('updated', 'skipped'));

        return self::SUCCESS;
    }

    /**
     * Build the update payload for one aggregated row, or null when the
     * product is missing or nothing about it would actually change.
     *
     * @return array{product: Product, changes: array<string, mixed>}|null
     */
    private function resolveUpdate(object $row, ?Product $product, bool $forceUpdate): ?array
    {
        if (! $product) {
            return null;
        }

        $avgSellingPrice = (float) $row->avg_selling_price;
        $avgUnitCost = (float) ($row->avg_unit_cost ?? 0);

        $changes = collect([
            'retail_price' => $avgSellingPrice > 0 && ($forceUpdate || $product->retail_price === null)
                ? round($avgSellingPrice, self::PRICE_PRECISION)
                : null,
            'purchase_price' => $avgUnitCost > 0 && ($forceUpdate || $product->purchase_price === null)
                ? round($avgUnitCost, self::PRICE_PRECISION)
                : null,
            'category_name' => $row->category_name && ($forceUpdate || $product->category_name === null)
                ? $row->category_name
                : null,
        ])->filter(fn ($value): bool => $value !== null);

        return $changes->isEmpty()
            ? null
            : ['product' => $product, 'changes' => $changes->all()];
    }
}
