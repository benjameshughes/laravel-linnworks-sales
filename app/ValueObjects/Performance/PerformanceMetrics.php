<?php

declare(strict_types=1);

namespace App\ValueObjects\Performance;

use JsonSerializable;

/**
 * Immutable value object for performance metrics
 */
final readonly class PerformanceMetrics implements JsonSerializable
{
    public function __construct(
        public string $operation,
        public float $durationMs,
        public int $memoryUsedBytes,
        public int $peakMemoryBytes,
        public int $itemsProcessed,
        public array $metadata = [],
        public ?float $startTime = null,
        public ?float $endTime = null,
    ) {}

    /**
     * Create from start/end timestamps
     */
    public static function fromTimestamps(
        string $operation,
        float $startTime,
        float $endTime,
        int $memoryBefore,
        int $itemsProcessed = 0,
        array $metadata = []
    ): self {
        return new self(
            operation: $operation,
            durationMs: ($endTime - $startTime) * 1000,
            memoryUsedBytes: memory_get_usage() - $memoryBefore,
            peakMemoryBytes: memory_get_peak_usage(),
            itemsProcessed: $itemsProcessed,
            metadata: $metadata,
            startTime: $startTime,
            endTime: $endTime
        );
    }

    /**
     * Get duration in seconds
     */
    public function getDurationSeconds(): float
    {
        return $this->durationMs / 1000;
    }

    /**
     * Get memory used in MB
     */
    public function getMemoryUsedMB(): float
    {
        return round($this->memoryUsedBytes / 1024 / 1024, 2);
    }

    /**
     * Get peak memory in MB
     */
    public function getPeakMemoryMB(): float
    {
        return round($this->peakMemoryBytes / 1024 / 1024, 2);
    }

    /**
     * Get throughput (items per second)
     */
    public function getThroughput(): float
    {
        if ($this->getDurationSeconds() === 0.0) {
            return 0.0;
        }

        return round($this->itemsProcessed / $this->getDurationSeconds(), 2);
    }

    /**
     * Get average time per item in milliseconds
     */
    public function getAverageTimePerItemMs(): float
    {
        if ($this->itemsProcessed === 0) {
            return 0.0;
        }

        return round($this->durationMs / $this->itemsProcessed, 2);
    }

    /**
     * Check if performance is slow (configurable thresholds)
     */
    public function isSlow(float $thresholdMs = 1000): bool
    {
        return $this->durationMs > $thresholdMs;
    }

    /**
     * Check if memory usage is high (configurable threshold in MB)
     */
    public function isMemoryHeavy(int $thresholdMB = 128): bool
    {
        return $this->getMemoryUsedMB() > $thresholdMB;
    }

    /**
     * Get performance grade (A-F)
     */
    public function getGrade(): string
    {
        $throughput = $this->getThroughput();

        return match (true) {
            $throughput >= 100 => 'A',
            $throughput >= 50 => 'B',
            $throughput >= 25 => 'C',
            $throughput >= 10 => 'D',
            default => 'F',
        };
    }

    /**
     * Add metadata
     */
    public function withMetadata(array $additionalMetadata): self
    {
        return new self(
            operation: $this->operation,
            durationMs: $this->durationMs,
            memoryUsedBytes: $this->memoryUsedBytes,
            peakMemoryBytes: $this->peakMemoryBytes,
            itemsProcessed: $this->itemsProcessed,
            metadata: array_merge($this->metadata, $additionalMetadata),
            startTime: $this->startTime,
            endTime: $this->endTime
        );
    }

    /**
     * Convert to array for logging
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'duration_ms' => round($this->durationMs, 2),
            'duration_s' => $this->getDurationSeconds(),
            'memory_used_mb' => $this->getMemoryUsedMB(),
            'peak_memory_mb' => $this->getPeakMemoryMB(),
            'items_processed' => $this->itemsProcessed,
            'throughput' => $this->getThroughput(),
            'avg_time_per_item_ms' => $this->getAverageTimePerItemMs(),
            'grade' => $this->getGrade(),
            'is_slow' => $this->isSlow(),
            'is_memory_heavy' => $this->isMemoryHeavy(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * JSON serialize
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return sprintf(
            '%s: %.2fms (%d items @ %.2f/s) [%s]',
            $this->operation,
            $this->durationMs,
            $this->itemsProcessed,
            $this->getThroughput(),
            $this->getGrade()
        );
    }

    /**
     * Create summary for multiple metrics
     */
    public static function summarize(array $metrics): array
    {
        if (empty($metrics)) {
            return [
                'total_operations' => 0,
                'total_duration_ms' => 0,
                'total_items' => 0,
                'average_throughput' => 0,
            ];
        }

        $totalDuration = 0;
        $totalItems = 0;
        $totalMemory = 0;

        foreach ($metrics as $metric) {
            if ($metric instanceof self) {
                $totalDuration += $metric->durationMs;
                $totalItems += $metric->itemsProcessed;
                $totalMemory += $metric->memoryUsedBytes;
            }
        }

        return [
            'total_operations' => count($metrics),
            'total_duration_ms' => round($totalDuration, 2),
            'total_duration_s' => round($totalDuration / 1000, 2),
            'total_items' => $totalItems,
            'total_memory_mb' => round($totalMemory / 1024 / 1024, 2),
            'average_throughput' => $totalDuration > 0 ? round($totalItems / ($totalDuration / 1000), 2) : 0,
            'average_duration_ms' => count($metrics) > 0 ? round($totalDuration / count($metrics), 2) : 0,
        ];
    }
}
