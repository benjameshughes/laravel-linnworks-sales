<?php

declare(strict_types=1);

namespace App\Actions\Calculations\Profitability;

use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

final class CalculateCostDataCoverage
{
    public function __invoke(): array
    {
        $totalProducts = Product::where('is_active', true)->count();
        $withCostPrice = Product::where('is_active', true)
            ->whereNotNull('purchase_price')
            ->where('purchase_price', '>', 0)
            ->count();
        $withShippingCost = Product::where('is_active', true)
            ->whereNotNull('shipping_cost')
            ->where('shipping_cost', '>', 0)
            ->count();

        $totalItems = OrderItem::count();
        $itemsWithUnitCost = OrderItem::where('unit_cost', '>', 0)->count();

        $totalRevenue = (float) DB::table('order_items')
            ->selectRaw('SUM(CASE WHEN line_total > 0 THEN line_total ELSE quantity * price_per_unit END) as total')
            ->value('total');

        $coveredRevenue = (float) DB::table('order_items as oi')
            ->leftJoin('products as p', 'oi.sku', '=', 'p.sku')
            ->where(function ($q) {
                $q->where('oi.unit_cost', '>', 0)
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('p.purchase_price')
                            ->where('p.purchase_price', '>', 0);
                    });
            })
            ->selectRaw('SUM(CASE WHEN oi.line_total > 0 THEN oi.line_total ELSE oi.quantity * oi.price_per_unit END) as total')
            ->value('total');

        return [
            'products_total' => $totalProducts,
            'products_with_cost' => $withCostPrice,
            'products_cost_percent' => $totalProducts > 0
                ? round(($withCostPrice / $totalProducts) * 100, 1)
                : 0,
            'products_with_shipping' => $withShippingCost,
            'products_shipping_percent' => $totalProducts > 0
                ? round(($withShippingCost / $totalProducts) * 100, 1)
                : 0,
            'items_total' => $totalItems,
            'items_with_cost' => $itemsWithUnitCost,
            'items_cost_percent' => $totalItems > 0
                ? round(($itemsWithUnitCost / $totalItems) * 100, 1)
                : 0,
            'revenue_total' => $totalRevenue,
            'revenue_covered' => $coveredRevenue,
            'revenue_coverage_percent' => $totalRevenue > 0
                ? round(($coveredRevenue / $totalRevenue) * 100, 1)
                : 0,
        ];
    }
}
