<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Events\ImportBatchProcessed;
use App\Events\ImportPerformanceUpdate;
use App\Models\SyncLog;

/**
 * Track and broadcast sync progress
 *
 * Single responsibility: Managing sync progress state and broadcasting.
 * Separates progress tracking from business logic.
 */
final readonly class TrackSyncProgress
{
    public function __construct(
        private float $startTime,
        private SyncLog $syncLog,
    ) {}

    /**
     * Create a new progress tracker
     */
    public static function start(SyncLog $syncLog): self
    {
        return new self(
            startTime: microtime(true),
            syncLog: $syncLog,
        );
    }

    /**
     * Broadcast batch progress with performance metrics
     */
    public function broadcastBatchProgress(
        int $currentBatch,
        int $totalBatches,
        int $ordersInBatch,
        int $totalProcessed,
        int $created,
        int $updated
    ): void {
        $timeElapsed = microtime(true) - $this->startTime;
        $ordersPerSecond = $totalProcessed > 0 ? $totalProcessed / max(0.001, $timeElapsed) : 0;
        $estimatedRemaining = null;

        if ($currentBatch < $totalBatches && $timeElapsed > 0) {
            $avgTimePerBatch = $timeElapsed / $currentBatch;
            $remainingBatches = $totalBatches - $currentBatch;
            $estimatedRemaining = $avgTimePerBatch * $remainingBatches;
        }

        event(new ImportBatchProcessed(
            batchNumber: $currentBatch,
            totalBatches: $totalBatches,
            ordersInBatch: $ordersInBatch,
            totalProcessed: $totalProcessed,
            created: $created,
            updated: $updated,
            ordersPerSecond: $ordersPerSecond,
            memoryMb: memory_get_usage(true) / 1024 / 1024,
            timeElapsed: $timeElapsed,
            estimatedRemaining: $estimatedRemaining,
        ));
    }

    /**
     * Broadcast aggregate performance update
     */
    public function broadcastPerformanceUpdate(
        int $totalProcessed,
        int $created,
        int $updated,
        int $failed,
        int $currentBatch,
        int $totalBatches
    ): void {
        $timeElapsed = microtime(true) - $this->startTime;
        $ordersPerSecond = $totalProcessed > 0 ? $totalProcessed / max(0.001, $timeElapsed) : 0;

        event(new ImportPerformanceUpdate(
            totalProcessed: $totalProcessed,
            created: $created,
            updated: $updated,
            failed: $failed,
            avgSpeed: $ordersPerSecond,
            peakMemory: memory_get_peak_usage(true) / 1024 / 1024,
            duration: $timeElapsed,
            currentOperation: $currentBatch === $totalBatches
                ? 'Completed'
                : "Processing batch {$currentBatch}/{$totalBatches}",
        ));
    }

    /**
     * Persist progress to database
     */
    public function persistProgress(
        string $phase,
        int $current,
        int $total,
        int $totalProcessed,
        int $created,
        int $updated,
        int $failed,
        int $currentBatch,
        int $totalBatches
    ): void {
        $timeElapsed = microtime(true) - $this->startTime;
        $ordersPerSecond = $totalProcessed > 0 ? $totalProcessed / max(0.001, $timeElapsed) : 0;
        $estimatedRemaining = null;

        if ($currentBatch < $totalBatches && $timeElapsed > 0) {
            $avgTimePerBatch = $timeElapsed / $currentBatch;
            $remainingBatches = $totalBatches - $currentBatch;
            $estimatedRemaining = $avgTimePerBatch * $remainingBatches;
        }

        $this->syncLog->updateProgress($phase, $current, $total, [
            'message' => "Imported batch {$currentBatch}/{$totalBatches}",
            'current_batch' => $currentBatch,
            'total_batches' => $totalBatches,
            'total_processed' => $totalProcessed,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'orders_per_second' => round($ordersPerSecond, 2),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'time_elapsed' => round($timeElapsed, 2),
            'estimated_remaining' => $estimatedRemaining ? round($estimatedRemaining, 2) : null,
        ]);
    }

    /**
     * Get elapsed time in seconds
     */
    public function getElapsedTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Get orders per second rate
     */
    public function getOrdersPerSecond(int $totalProcessed): float
    {
        $elapsed = $this->getElapsedTime();

        return $totalProcessed > 0 ? $totalProcessed / max(0.001, $elapsed) : 0;
    }
}
