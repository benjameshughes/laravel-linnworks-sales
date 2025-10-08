<?php

declare(strict_types=1);

namespace App\Jobs\Linnworks;

use App\Actions\Linnworks\Orders\ImportOrders;
use App\DataTransferObjects\LinnworksOrder;
use App\Models\FailedOrderSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryFailedSyncs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /**
     * Execute the job to retry failed order syncs.
     */
    public function handle(ImportOrders $importOrders): void
    {
        $failedSyncs = FailedOrderSync::query()
            ->readyForRetry()
            ->limit(50)
            ->get();

        if ($failedSyncs->isEmpty()) {
            Log::info('No failed syncs ready for retry');
            return;
        }

        Log::info('Retrying failed order syncs', [
            'count' => $failedSyncs->count(),
        ]);

        $resolved = 0;
        $stillFailing = 0;

        foreach ($failedSyncs as $failedSync) {
            try {
                $this->retrySync($failedSync, $importOrders);
                $resolved++;
            } catch (Throwable $e) {
                $stillFailing++;
                $this->handleRetryFailure($failedSync, $e);
            }
        }

        Log::info('Failed sync retry completed', [
            'attempted' => $failedSyncs->count(),
            'resolved' => $resolved,
            'still_failing' => $stillFailing,
        ]);
    }

    /**
     * Retry a single failed sync
     */
    private function retrySync(FailedOrderSync $failedSync, ImportOrders $importOrders): void
    {
        if (!$failedSync->order_data) {
            Log::warning('Cannot retry sync without order data', [
                'failed_sync_id' => $failedSync->id,
                'order_identifier' => $failedSync->order_identifier,
            ]);
            $failedSync->recordRetryFailure();
            return;
        }

        // Attempt to recreate the DTO and import
        $linnworksOrder = LinnworksOrder::fromArray($failedSync->order_data);

        $result = $importOrders->handle([$linnworksOrder], forceUpdate: true);

        if ($result->failed === 0 && ($result->created > 0 || $result->updated > 0)) {
            $failedSync->markResolved();

            Log::info('Failed sync resolved successfully', [
                'failed_sync_id' => $failedSync->id,
                'order_identifier' => $failedSync->order_identifier,
                'attempt_count' => $failedSync->attempt_count,
            ]);
        } else {
            throw new \RuntimeException('Import failed to create or update order');
        }
    }

    /**
     * Handle a retry failure
     */
    private function handleRetryFailure(FailedOrderSync $failedSync, Throwable $exception): void
    {
        $failedSync->recordRetryFailure();

        Log::warning('Failed sync retry failed', [
            'failed_sync_id' => $failedSync->id,
            'order_identifier' => $failedSync->order_identifier,
            'attempt_count' => $failedSync->attempt_count,
            'exception' => $exception->getMessage(),
        ]);

        // Alert on third failure
        if ($failedSync->has_exceeded_max_retries) {
            Log::error('Failed sync exceeded maximum retries', [
                'failed_sync_id' => $failedSync->id,
                'order_identifier' => $failedSync->order_identifier,
                'attempt_count' => $failedSync->attempt_count,
                'failure_reason' => $failedSync->failure_reason,
            ]);
        }
    }
}
