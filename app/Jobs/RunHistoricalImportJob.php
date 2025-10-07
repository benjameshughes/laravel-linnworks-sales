<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ImportCompleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

final class RunHistoricalImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Allow plenty of time for large imports (60 minutes).
     */
    public int $timeout = 3600;

    /**
     * Avoid retry loops on API failures.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $fromDate,
        private readonly string $toDate,
        private readonly int $batchSize,
    ) {
        $this->queue = 'imports';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting queued historical import', [
            'from' => $this->fromDate,
            'to' => $this->toDate,
            'batch_size' => $this->batchSize,
        ]);

        try {
            Artisan::call('import:historical-orders', [
                '--from' => $this->fromDate,
                '--to' => $this->toDate,
                '--batch-size' => $this->batchSize,
                '--force' => true,
            ]);

            Log::info('Queued historical import finished', [
                'from' => $this->fromDate,
                'to' => $this->toDate,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Queued historical import failed', [
                'from' => $this->fromDate,
                'to' => $this->toDate,
                'error' => $exception->getMessage(),
            ]);

            event(new ImportCompleted(
                totalProcessed: 0,
                totalImported: 0,
                totalSkipped: 0,
                totalErrors: 0,
                success: false,
            ));

            throw $exception;
        }
    }
}
