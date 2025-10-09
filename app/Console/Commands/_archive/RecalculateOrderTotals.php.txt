<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateOrderTotals extends Command
{
    protected $signature = 'orders:recalculate-totals
                            {--open-only : Only recalculate open orders}';

    protected $description = 'Recalculate order totals from order items (for open orders that lack pricing data)';

    public function handle(): int
    {
        $this->info('Recalculating order totals from order items...');

        $query = Order::query();

        if ($this->option('open-only')) {
            $query->where('is_open', true);
            $this->info('Processing open orders only');
        }

        // Only process orders with 0 total_charge
        $query->where('total_charge', 0);

        $ordersToUpdate = $query->count();

        if ($ordersToUpdate === 0) {
            $this->info('No orders need recalculation');
            return self::SUCCESS;
        }

        $this->info("Found {$ordersToUpdate} orders to recalculate");

        $progressBar = $this->output->createProgressBar($ordersToUpdate);
        $progressBar->start();

        $updated = 0;
        $skipped = 0;

        $query->chunk(100, function ($orders) use (&$updated, &$skipped, $progressBar) {
            foreach ($orders as $order) {
                // Calculate totals from order_items
                // Use line_total if available, otherwise calculate from quantity * price_per_unit
                $itemTotals = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->select(
                        DB::raw('SUM(CASE WHEN line_total > 0 THEN line_total ELSE quantity * price_per_unit END) as total_charge'),
                        DB::raw('SUM(quantity) as total_items')
                    )
                    ->first();

                if ($itemTotals && $itemTotals->total_charge > 0) {
                    $order->update([
                        'total_charge' => $itemTotals->total_charge,
                        'total_paid' => $itemTotals->total_charge, // Assume fully paid
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Recalculation complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Orders Updated', $updated],
                ['Orders Skipped (no items)', $skipped],
            ]
        );

        return self::SUCCESS;
    }
}
