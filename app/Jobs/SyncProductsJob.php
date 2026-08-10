<?php

declare(strict_types=1);

namespace App\Jobs;

use Throwable;
use App\Models\SyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use App\Actions\Linnworks\SyncProducts;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

final class SyncProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DATA_REQUIREMENTS = ['StockLevels', 'Pricing'];

    public readonly int $uniqueFor;

    public readonly int $tries;

    public readonly int $timeout;

    public function __construct(
        public readonly ?string $startedBy = null,
        public readonly int $maxProducts = 10000,
    ) {
        $this->uniqueFor = 3600;
        $this->tries = 3;
        $this->timeout = 900; // 15 minutes
        $this->onQueue('medium');
    }

    public function uniqueId(): string
    {
        return 'sync-products';
    }

    public function handle(SyncProducts $syncProducts): void
    {
        $syncLog = SyncLog::startSync(SyncLog::TYPE_PRODUCTS, [
            'started_by' => $this->startedBy ?? 'system',
            'max_products' => $this->maxProducts,
        ]);

        Log::info('Product sync started', [
            'started_by' => $this->startedBy,
            'max_products' => $this->maxProducts,
        ]);

        $result = $syncProducts(dataRequirements: self::DATA_REQUIREMENTS);

        $syncLog->complete(
            fetched: $result['synced'],
            created: $result['created'],
            updated: $result['updated'],
            skipped: $result['synced'] - $result['created'] - $result['updated'] - $result['failed'],
            failed: $result['failed'],
        );

        Log::info('Product sync completed', $result);
    }

    /**
     * Runs once the final retry has been exhausted, so the sync log is only
     * marked failed when the job is genuinely dead rather than mid-retry.
     */
    public function failed(Throwable $exception): void
    {
        SyncLog::getActiveSync(SyncLog::TYPE_PRODUCTS)?->fail($exception->getMessage());

        Log::error('SyncProductsJob failed permanently', [
            'started_by' => $this->startedBy,
            'error' => $exception->getMessage(),
        ]);
    }
}
