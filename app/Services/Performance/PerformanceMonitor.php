<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\ValueObjects\Performance\PerformanceMetrics;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Service for monitoring and tracking performance metrics
 */
class PerformanceMonitor
{
    private array $activeOperations = [];

    private array $completedMetrics = [];

    /**
     * Start tracking an operation
     */
    public function start(string $operation): string
    {
        $trackingId = uniqid($operation.'_', true);

        $this->activeOperations[$trackingId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'metadata' => [],
        ];

        return $trackingId;
    }

    /**
     * Stop tracking an operation
     */
    public function stop(string $trackingId, int $itemsProcessed = 0, array $metadata = []): PerformanceMetrics
    {
        if (! isset($this->activeOperations[$trackingId])) {
            throw new \InvalidArgumentException("Unknown tracking ID: {$trackingId}");
        }

        $operation = $this->activeOperations[$trackingId];
        $endTime = microtime(true);

        $metrics = PerformanceMetrics::fromTimestamps(
            operation: $operation['operation'],
            startTime: $operation['start_time'],
            endTime: $endTime,
            memoryBefore: $operation['start_memory'],
            itemsProcessed: $itemsProcessed,
            metadata: array_merge($operation['metadata'], $metadata)
        );

        // Store completed metrics
        $this->completedMetrics[] = $metrics;

        // Clean up active operation
        unset($this->activeOperations[$trackingId]);

        // Log if slow or memory-heavy
        if ($metrics->isSlow() || $metrics->isMemoryHeavy()) {
            Log::warning('Slow operation detected', $metrics->toArray());
        } else {
            Log::debug('Operation completed', $metrics->toArray());
        }

        return $metrics;
    }

    /**
     * Add metadata to an active operation
     */
    public function addMetadata(string $trackingId, array $metadata): void
    {
        if (isset($this->activeOperations[$trackingId])) {
            $this->activeOperations[$trackingId]['metadata'] = array_merge(
                $this->activeOperations[$trackingId]['metadata'],
                $metadata
            );
        }
    }

    /**
     * Track a closure execution and return its result
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function measure(string $operation, Closure $callback, array $metadata = []): mixed
    {
        $trackingId = $this->start($operation);

        try {
            $result = $callback();

            // Try to determine items processed from result
            $itemsProcessed = $this->determineItemsCount($result);

            $this->stop($trackingId, $itemsProcessed, $metadata);

            return $result;
        } catch (\Throwable $e) {
            // Still record metrics even on failure
            $this->stop($trackingId, 0, array_merge($metadata, [
                'failed' => true,
                'error' => $e->getMessage(),
            ]));

            throw $e;
        }
    }

    /**
     * Track a closure execution with explicit item count
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function measureWithCount(
        string $operation,
        Closure $callback,
        int $expectedItemCount,
        array $metadata = []
    ): mixed {
        $trackingId = $this->start($operation);

        try {
            $result = $callback();
            $this->stop($trackingId, $expectedItemCount, $metadata);

            return $result;
        } catch (\Throwable $e) {
            $this->stop($trackingId, 0, array_merge($metadata, [
                'failed' => true,
                'error' => $e->getMessage(),
            ]));
            throw $e;
        }
    }

    /**
     * Get all completed metrics
     *
     * @return array<PerformanceMetrics>
     */
    public function getCompletedMetrics(): array
    {
        return $this->completedMetrics;
    }

    /**
     * Get metrics for a specific operation
     *
     * @return array<PerformanceMetrics>
     */
    public function getMetricsForOperation(string $operation): array
    {
        return array_filter(
            $this->completedMetrics,
            fn (PerformanceMetrics $m) => $m->operation === $operation
        );
    }

    /**
     * Get summary of all completed operations
     */
    public function getSummary(): array
    {
        return PerformanceMetrics::summarize($this->completedMetrics);
    }

    /**
     * Get summary grouped by operation
     */
    public function getSummaryByOperation(): array
    {
        $grouped = [];

        foreach ($this->completedMetrics as $metric) {
            $operation = $metric->operation;

            if (! isset($grouped[$operation])) {
                $grouped[$operation] = [];
            }

            $grouped[$operation][] = $metric;
        }

        $summary = [];
        foreach ($grouped as $operation => $metrics) {
            $summary[$operation] = PerformanceMetrics::summarize($metrics);
        }

        return $summary;
    }

    /**
     * Clear all completed metrics
     */
    public function clearMetrics(): void
    {
        $this->completedMetrics = [];
    }

    /**
     * Get currently active operations
     */
    public function getActiveOperations(): array
    {
        return array_map(function ($operation) {
            return [
                'operation' => $operation['operation'],
                'running_for_ms' => (microtime(true) - $operation['start_time']) * 1000,
                'metadata' => $operation['metadata'],
            ];
        }, $this->activeOperations);
    }

    /**
     * Attempt to determine item count from result
     */
    private function determineItemsCount(mixed $result): int
    {
        if (is_countable($result)) {
            return count($result);
        }

        if ($result instanceof \Illuminate\Support\Collection) {
            return $result->count();
        }

        if (is_object($result) && method_exists($result, 'count')) {
            return $result->count();
        }

        // Check for common result object properties
        if (is_object($result)) {
            if (isset($result->total)) {
                return (int) $result->total;
            }
            if (isset($result->created)) {
                return (int) $result->created;
            }
            if (isset($result->processed)) {
                return (int) $result->processed;
            }
        }

        return 0;
    }

    /**
     * Log performance summary
     */
    public function logSummary(?string $context = null): void
    {
        $summary = $this->getSummary();

        $logContext = array_merge(
            ['context' => $context ?? 'performance_summary'],
            $summary
        );

        Log::info('Performance summary', $logContext);
    }

    /**
     * Log operation-specific summaries
     */
    public function logDetailedSummary(?string $context = null): void
    {
        $byOperation = $this->getSummaryByOperation();

        foreach ($byOperation as $operation => $summary) {
            Log::info("Performance summary: {$operation}", array_merge(
                ['context' => $context, 'operation' => $operation],
                $summary
            ));
        }
    }
}
