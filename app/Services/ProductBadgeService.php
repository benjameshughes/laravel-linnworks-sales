<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\OrderItem;
use App\Enums\ProductBadgeType;
use App\ValueObjects\DateRange;
use App\ValueObjects\ProductBadge;
use Illuminate\Support\Collection;
use App\ValueObjects\ProductMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;

final readonly class ProductBadgeService
{
    public function __construct(
        private float $hotSellerThreshold = 2.0,
        private float $growthThreshold = 20.0,
        private float $marginThreshold = 30.0,
        private int $newProductDays = 30,
        private float $consistentThreshold = 0.75,
        private float $highVolumePercentile = 0.8,
    ) {}

    /**
     * @return Collection<ProductBadge>
     */
    public function getProductBadges(Product $product, int $period = 30): Collection
    {
        $cacheKey = "product_badges:{$product->sku}:{$period}";

        return Cache::remember($cacheKey, now()->addHour(),
            fn () => $this->calculateBadges($product, $period)
        );
    }

    /**
     * @param  Collection<Product>  $products
     * @return array<string, Collection<ProductBadge>> Keyed by SKU
     */
    public function getBulkProductBadges(Collection $products, int $period = 30): array
    {
        $skus = $products->pluck('sku')->all();
        $dateRange = $this->createDateRange($period);
        $previousRange = new DateRange(from: now()->subDays($period * 2), to: now()->subDays($period));

        $salesByProduct = OrderItem::query()
            ->whereIn('sku', $skus)
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('received_at', [$dateRange->from, $dateRange->to]))
            ->with('order:id,received_at')
            ->get()
            ->groupBy('sku');

        $currentQuantities = OrderItem::query()
            ->whereIn('sku', $skus)
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('received_at', [$dateRange->from, $dateRange->to]))
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku');

        $previousQuantities = OrderItem::query()
            ->whereIn('sku', $skus)
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('received_at', [$previousRange->from, $previousRange->to]))
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity', 'sku');

        $weeklyData = OrderItem::query()
            ->whereIn('order_items.sku', $skus)
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.received_at', '>=', now()->subDays($period))
            ->selectRaw('order_items.sku, orders.received_at')
            ->get()
            ->groupBy('sku')
            ->map(fn (Collection $items) => $items
                ->map(fn ($item) => \Carbon\Carbon::parse($item->received_at)->startOfWeek()->format('Y-W'))
                ->unique()
                ->count()
            );

        $volumeThreshold = Cache::remember(
            "high_volume_threshold:{$period}",
            now()->addHour(),
            fn () => $this->calculateVolumeThreshold($period)
        );

        $weeks = max(1, now()->subDays($period)->diffInWeeks(now()));
        $result = [];

        foreach ($products as $product) {
            $cacheKey = "product_badges:{$product->sku}:{$period}";
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $result[$product->sku] = $cached;

                continue;
            }

            $salesData = $salesByProduct->get($product->sku, collect());
            $badges = collect();

            if ($salesData->isEmpty()) {
                $badges->push(new ProductBadge(ProductBadgeType::NO_SALES));
                Cache::put($cacheKey, $badges, now()->addHour());
                $result[$product->sku] = $badges;

                continue;
            }

            $metrics = $this->calculateProductMetrics($salesData, $period);

            $prev = (int) ($previousQuantities->get($product->sku, 0));
            $curr = (int) ($currentQuantities->get($product->sku, 0));
            $growthRate = $prev > 0 ? (($curr - $prev) / $prev) * 100 : 0.0;

            $badges = $this->getPerformanceBadges($metrics, $growthRate);

            if ($product->created_at?->isAfter(now()->subDays($this->newProductDays))) {
                $badges->push(new ProductBadge(
                    type: ProductBadgeType::NEW_PRODUCT,
                    metadata: ['days_old' => $product->created_at->diffInDays(now())]
                ));
            }

            if ($metrics->totalQuantity >= $volumeThreshold) {
                $badges->push(new ProductBadge(
                    type: ProductBadgeType::HIGH_VOLUME,
                    metadata: ['total_quantity' => $metrics->totalQuantity]
                ));
            }

            $weeksWithSales = $weeklyData->get($product->sku, 0);
            if ($weeks >= 2 && ($weeksWithSales / $weeks) >= $this->consistentThreshold) {
                $badges->push(new ProductBadge(ProductBadgeType::CONSISTENT));
            }

            $badges = $badges->sortBy('priority')->values();
            Cache::put($cacheKey, $badges, now()->addHour());
            $result[$product->sku] = $badges;
        }

        return $result;
    }

    /**
     * @return Collection<ProductBadge>
     */
    private function calculateBadges(Product $product, int $period): Collection
    {
        $badges = collect();
        $dateRange = $this->createDateRange($period);
        $salesData = $this->getProductSalesData($product->sku, $dateRange);

        if ($salesData->isEmpty()) {
            return $badges->push(new ProductBadge(ProductBadgeType::NO_SALES));
        }

        $metrics = $this->calculateProductMetrics($salesData, $period);
        $growthRate = $this->calculateGrowthRate($product->sku, $period);

        return $this->buildBadgeCollection($badges, $product, $metrics, $growthRate, $period);
    }

    private function createDateRange(int $period): DateRange
    {
        return DateRange::fromPeriod($period);
    }

    /**
     * @return Collection<OrderItem>
     */
    private function getProductSalesData(string $sku, DateRange $dateRange): Collection
    {
        return OrderItem::query()
            ->where('sku', $sku)
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('received_at', [$dateRange->from, $dateRange->to])
            )
            ->with('order:id,received_at')
            ->get();
    }

    private function calculateProductMetrics(Collection $salesData, int $period): ProductMetrics
    {
        $totalRevenue = $salesData->sum('line_total');
        $totalQuantity = $salesData->sum('quantity');
        $totalCost = $salesData->sum(fn (OrderItem $item) => $item->unit_cost * $item->quantity);
        $orderCount = $salesData->unique('order_id')->count();

        return new ProductMetrics(
            totalRevenue: $totalRevenue,
            totalQuantity: $totalQuantity,
            totalCost: $totalCost,
            period: $period,
            orderCount: $orderCount,
        );
    }

    private function calculateGrowthRate(string $sku, int $period): float
    {
        $previousRange = new DateRange(
            from: now()->subDays($period * 2),
            to: now()->subDays($period),
        );

        $currentRange = new DateRange(
            from: now()->subDays($period),
            to: now(),
        );

        [$previousQuantity, $currentQuantity] = [
            $this->getQuantityForPeriod($sku, $previousRange),
            $this->getQuantityForPeriod($sku, $currentRange),
        ];

        return $previousQuantity > 0
            ? (($currentQuantity - $previousQuantity) / $previousQuantity) * 100
            : 0.0;
    }

    private function getQuantityForPeriod(string $sku, DateRange $dateRange): int
    {
        return (int) OrderItem::query()
            ->where('sku', $sku)
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('received_at', [$dateRange->from, $dateRange->to])
            )
            ->sum('quantity');
    }

    /**
     * @return Collection<ProductBadge>
     */
    private function buildBadgeCollection(
        Collection $badges,
        Product $product,
        ProductMetrics $metrics,
        float $growthRate,
        int $period
    ): Collection {
        return $badges
            ->merge($this->getPerformanceBadges($metrics, $growthRate))
            ->merge($this->getAgeBadges($product))
            ->merge($this->getVolumeBadges($product->sku, $metrics, $period))
            ->sortBy('priority')
            ->values();
    }

    /**
     * @return Collection<ProductBadge>
     */
    private function getPerformanceBadges(ProductMetrics $metrics, float $growthRate): Collection
    {
        $badges = collect();

        if ($metrics->avgDailySales() >= $this->hotSellerThreshold) {
            $badges->push(new ProductBadge(
                type: ProductBadgeType::HOT_SELLER,
                metadata: ['daily_sales' => $metrics->avgDailySales()]
            ));
        }

        match (true) {
            $growthRate > $this->growthThreshold => $badges->push(new ProductBadge(
                type: ProductBadgeType::GROWING,
                metadata: ['growth_rate' => $growthRate]
            )),
            $growthRate < -$this->growthThreshold => $badges->push(new ProductBadge(
                type: ProductBadgeType::DECLINING,
                metadata: ['growth_rate' => $growthRate]
            )),
            default => null,
        };

        if ($metrics->profitMargin() > $this->marginThreshold) {
            $badges->push(new ProductBadge(
                type: ProductBadgeType::TOP_MARGIN,
                metadata: ['profit_margin' => $metrics->profitMargin()]
            ));
        }

        return $badges;
    }

    /**
     * @return Collection<ProductBadge>
     */
    private function getAgeBadges(Product $product): Collection
    {
        $badges = collect();

        if ($product->created_at?->isAfter(now()->subDays($this->newProductDays))) {
            $daysOld = $product->created_at->diffInDays(now());
            $badges->push(new ProductBadge(
                type: ProductBadgeType::NEW_PRODUCT,
                metadata: ['days_old' => $daysOld]
            ));
        }

        return $badges;
    }

    /**
     * @return Collection<ProductBadge>
     */
    private function getVolumeBadges(string $sku, ProductMetrics $metrics, int $period): Collection
    {
        $badges = collect();

        if ($this->isHighVolumeProduct($sku, $metrics->totalQuantity, $period)) {
            $badges->push(new ProductBadge(
                type: ProductBadgeType::HIGH_VOLUME,
                metadata: ['total_quantity' => $metrics->totalQuantity]
            ));
        }

        if ($this->isConsistentSeller($sku, $period)) {
            $badges->push(new ProductBadge(ProductBadgeType::CONSISTENT));
        }

        return $badges;
    }

    private function isHighVolumeProduct(string $sku, int $quantity, int $period): bool
    {
        $threshold = Cache::remember(
            key: "high_volume_threshold:{$period}",
            ttl: now()->addHour(),
            callback: fn () => $this->calculateVolumeThreshold($period)
        );

        return $quantity >= $threshold;
    }

    private function calculateVolumeThreshold(int $period): int
    {
        $dateRange = $this->createDateRange($period);

        $quantities = OrderItem::query()
            ->whereHas('order', fn (Builder $query) => $query->whereBetween('received_at', [$dateRange->from, $dateRange->to])
            )
            ->selectRaw('sku, SUM(quantity) as total_quantity')
            ->groupBy('sku')
            ->pluck('total_quantity')
            ->sort()
            ->values();

        if ($quantities->isEmpty()) {
            return 0;
        }

        $thresholdIndex = (int) ($quantities->count() * $this->highVolumePercentile);

        return (int) $quantities->get($thresholdIndex, 0);
    }

    private function isConsistentSeller(string $sku, int $period): bool
    {
        $weeks = max(1, now()->subDays($period)->diffInWeeks(now()));

        if ($weeks < 2) {
            return false;
        }

        $fromDate = now()->subDays($period);

        // Single query to get all order dates, then count distinct weeks in PHP
        // This reduces N queries to 1 query while remaining database-agnostic
        $orderDates = OrderItem::query()
            ->where('order_items.sku', $sku)
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.received_at', '>=', $fromDate)
            ->pluck('orders.received_at');

        $weeksWithSales = $orderDates
            ->map(fn ($date) => \Carbon\Carbon::parse($date)->startOfWeek()->format('Y-W'))
            ->unique()
            ->count();

        return ($weeksWithSales / $weeks) >= $this->consistentThreshold;
    }

    /**
     * @return Collection<string, Collection>
     */
    public function getBadgeDefinitions(): Collection
    {
        return collect(ProductBadgeType::cases())
            ->mapWithKeys(fn (ProductBadgeType $badge) => [
                $badge->value => collect([
                    'label' => $badge->label(),
                    'description' => $badge->description(),
                    'color' => $badge->color(),
                    'icon' => $badge->icon(),
                    'priority' => $badge->priority(),
                ]),
            ]);
    }
}
