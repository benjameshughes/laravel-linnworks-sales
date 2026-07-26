<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\LinnworksApiService;

final class UpdateUnknownChannels extends Command
{
    private const ORDER_CHUNK_SIZE = 50;

    private const SAMPLE_LIMIT = 10;

    protected $signature = 'orders:update-unknown-channels
                            {--limit= : Maximum number of orders to update}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Update orders with "Unknown" channel by fetching from Linnworks API';

    private int $totalProcessed = 0;

    private int $totalUpdated = 0;

    private int $totalSkipped = 0;

    private int $totalErrors = 0;

    public function __construct(
        private readonly LinnworksApiService $apiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->apiService->isConfigured()) {
            $this->error('❌ Linnworks API is not configured.');

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('🔄 Updating orders with Unknown channel...');

        // Get orders with Unknown channel that have Linnworks IDs
        $query = Order::where('source', 'Unknown')
            ->whereNotNull('order_id');

        if ($limit) {
            $query->limit($limit);
        }

        $unknownOrders = $query->get();
        $totalOrders = $unknownOrders->count();

        if ($totalOrders === 0) {
            $this->info('✨ No orders with Unknown channel found!');

            return self::SUCCESS;
        }

        $this->info("📊 Found {$totalOrders} orders with Unknown channel");

        if (! $isDryRun && ! $this->confirm('Do you want to continue?')) {
            $this->info('Update cancelled.');

            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($totalOrders);
        $progressBar->start();

        // Process in chunks of 50 to respect rate limits
        foreach ($unknownOrders->chunk(self::ORDER_CHUNK_SIZE) as $chunk) {
            $orderIds = $chunk->pluck('order_id')->filter()->toArray();

            if (empty($orderIds)) {
                $progressBar->advance($chunk->count());

                continue;
            }

            try {
                // Fetch detailed orders from Linnworks
                $detailedOrders = $this->apiService->getProcessedOrdersWithDetails($orderIds);

                foreach ($chunk as $localOrder) {
                    $this->totalProcessed++;

                    try {
                        // Find matching detailed order
                        $detailedOrder = $detailedOrders->firstWhere(function ($order) use ($localOrder) {
                            $orderId = is_array($order)
                                ? ($order['GeneralInfo']['pkOrderID'] ?? null)
                                : null;

                            return $orderId === $localOrder->order_id;
                        });

                        if (! $detailedOrder || ! isset($detailedOrder['GeneralInfo'])) {
                            $this->totalSkipped++;
                            $progressBar->advance();

                            continue;
                        }

                        $source = $detailedOrder['GeneralInfo']['Source'] ?? null;
                        $subSource = $detailedOrder['GeneralInfo']['SubSource'] ?? null;

                        if (! $source) {
                            $this->totalSkipped++;
                            $progressBar->advance();

                            continue;
                        }

                        if ($isDryRun) {
                            $this->newLine();
                            $this->line("[DRY RUN] Would update order {$localOrder->number}: {$source}".($subSource ? " / {$subSource}" : ''));
                            $this->totalUpdated++;
                        } else {
                            $updateData = ['source' => \Illuminate\Support\Str::lower(str_replace(' ', '_', $source))];
                            if ($subSource) {
                                $updateData['subsource'] = \Illuminate\Support\Str::lower(str_replace(' ', '_', $subSource));
                            }

                            $localOrder->update($updateData);
                            $this->totalUpdated++;

                            if ($this->totalUpdated % 50 === 0) {
                                $this->newLine();
                                $this->info("📈 Progress: {$this->totalUpdated} updated, {$this->totalSkipped} skipped");
                            }
                        }

                    } catch (\Exception $e) {
                        $this->totalErrors++;
                        Log::error('Failed to update order channel', [
                            'order_id' => $localOrder->id,
                            'number' => $localOrder->number,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $progressBar->advance();
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("❌ Error processing chunk: {$e->getMessage()}");
                Log::error('Update chunk error', [
                    'order_ids' => $orderIds,
                    'error' => $e->getMessage(),
                ]);
                $errors += count($orderIds);
                $progressBar->advance(count($orderIds));
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummary($isDryRun);

        return self::SUCCESS;
    }

    private function displaySummary(bool $isDryRun): void
    {
        $this->info('📊 Update Summary:');
        $this->table(['Metric', 'Count'], [
            ['Total Processed', number_format($this->totalProcessed)],
            ['Updated', number_format($this->totalUpdated)],
            ['Skipped (no data)', number_format($this->totalSkipped)],
            ['Errors', number_format($this->totalErrors)],
        ]);

        if ($isDryRun) {
            $this->info('🔍 This was a dry run - no data was modified.');
            $this->info('💡 Run without --dry-run to perform the actual update.');
        } elseif ($this->totalUpdated > 0) {
            $this->info("✅ Updated {$this->totalUpdated} orders!");

            $this->newLine();
            $this->info('📈 Channel Distribution:');
            $channels = Order::selectRaw('source, COUNT(*) as count')
                ->groupBy('source')
                ->orderByDesc('count')
                ->limit(self::SAMPLE_LIMIT)
                ->get();

            foreach ($channels as $channel) {
                $this->line('   '.str_pad($channel->source ?? 'NULL', 20).': '.number_format($channel->count));
            }
        }

        if ($this->totalErrors > 0) {
            $this->warn("⚠️  {$this->totalErrors} orders failed to update. Check logs for details.");
        }
    }
}
