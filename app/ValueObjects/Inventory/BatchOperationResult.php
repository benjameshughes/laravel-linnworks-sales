<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

use Illuminate\Support\Collection;

/**
 * Immutable value object representing the result of a batch operation.
 *
 * Uses PHP 8.2+ readonly properties and constructor property promotion.
 */
readonly class BatchOperationResult
{
    /**
     * @param  Collection<int, OperationResult>  $results
     */
    public function __construct(
        public Collection $results,
        public int $totalResults,
        public BatchResultStatus $status,
        public float $executionTimeMs,
    ) {}

    /**
     * Create from Linnworks API response.
     */
    public static function fromApiResponse(array $response, float $executionTimeMs): self
    {
        $results = collect($response['Results'] ?? [])
            ->map(fn (array $result) => OperationResult::fromApiResponse($result));

        $status = BatchResultStatus::from($response['ResultStatus'] ?? 'NOTSET');

        return new self(
            results: $results,
            totalResults: $response['TotalResults'] ?? $results->count(),
            status: $status,
            executionTimeMs: $executionTimeMs,
        );
    }

    /**
     * Get successful operations.
     */
    public function getSuccessful(): Collection
    {
        return $this->results->filter(fn (OperationResult $result) => $result->isSuccess());
    }

    /**
     * Get failed operations.
     */
    public function getFailed(): Collection
    {
        return $this->results->filter(fn (OperationResult $result) => ! $result->isSuccess());
    }

    /**
     * Get success count.
     */
    public function successCount(): int
    {
        return $this->getSuccessful()->count();
    }

    /**
     * Get failure count.
     */
    public function failureCount(): int
    {
        return $this->getFailed()->count();
    }

    /**
     * Get success rate as percentage.
     */
    public function successRate(): float
    {
        if ($this->totalResults === 0) {
            return 0.0;
        }

        return ($this->successCount() / $this->totalResults) * 100;
    }

    /**
     * Check if all operations succeeded.
     */
    public function isFullySuccessful(): bool
    {
        return $this->status === BatchResultStatus::SUCCESSFUL;
    }

    /**
     * Check if any operations succeeded.
     */
    public function isPartiallySuccessful(): bool
    {
        return $this->status === BatchResultStatus::PARTIALLY_SUCCESSFUL;
    }

    /**
     * Check if all operations failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === BatchResultStatus::FAILED;
    }

    /**
     * Get error messages from failed operations.
     */
    public function getErrorMessages(): Collection
    {
        return $this->getFailed()
            ->map(fn (OperationResult $result) => $result->errorMessage)
            ->filter();
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'total_results' => $this->totalResults,
            'status' => $this->status->value,
            'successful' => $this->successCount(),
            'failed' => $this->failureCount(),
            'success_rate' => round($this->successRate(), 2),
            'execution_time_ms' => round($this->executionTimeMs, 2),
            'errors' => $this->getErrorMessages()->toArray(),
        ];
    }

    /**
     * Get summary string for logging.
     */
    public function getSummary(): string
    {
        return sprintf(
            '%s: %d/%d succeeded (%.1f%%) in %.2fms',
            $this->status->name,
            $this->successCount(),
            $this->totalResults,
            $this->successRate(),
            $this->executionTimeMs
        );
    }
}
