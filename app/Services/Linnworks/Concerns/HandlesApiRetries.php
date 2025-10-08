<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Concerns;

use App\Exceptions\Linnworks\LinnworksApiException;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HandlesApiRetries
{
    /**
     * Execute a callback with exponential backoff retry logic.
     *
     * @template T
     * @param Closure(): T $callback
     * @param array<int> $backoffSchedule Backoff delays in seconds [1, 3, 10]
     * @return T
     * @throws LinnworksApiException
     */
    protected function withRetry(
        Closure $callback,
        array $backoffSchedule = [1, 3, 10],
        ?string $operation = null
    ): mixed {
        $maxAttempts = count($backoffSchedule) + 1;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $callback();
            } catch (LinnworksApiException $e) {
                $attempt++;

                // Don't retry if not retryable or last attempt
                if (!$e->isRetryable() || $attempt >= $maxAttempts) {
                    Log::error('Linnworks API request failed permanently', [
                        'operation' => $operation,
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'exception' => $e->getMessage(),
                        'context' => $e->context(),
                    ]);
                    throw $e;
                }

                // Calculate delay
                $delay = $this->calculateRetryDelay($e, $backoffSchedule, $attempt);

                Log::warning('Linnworks API request failed, retrying', [
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay_seconds' => $delay,
                    'is_rate_limited' => $e->isRateLimited(),
                    'exception' => $e->getMessage(),
                ]);

                sleep($delay);
            } catch (Throwable $e) {
                // Non-Linnworks exceptions should not be retried
                Log::error('Unexpected error during Linnworks API request', [
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        }

        throw new LinnworksApiException(
            message: 'Maximum retry attempts exceeded',
            context: ['operation' => $operation, 'max_attempts' => $maxAttempts]
        );
    }

    /**
     * Calculate retry delay based on exception type and backoff schedule.
     */
    protected function calculateRetryDelay(
        LinnworksApiException $exception,
        array $backoffSchedule,
        int $attempt
    ): int {
        // For rate limiting, use the Retry-After header if available
        if ($exception->isRateLimited()) {
            $retryAfter = $exception->getRetryAfter();
            if ($retryAfter !== null) {
                return min($retryAfter, 60); // Cap at 60 seconds
            }
        }

        // Use exponential backoff from schedule
        $index = $attempt - 1;
        return $backoffSchedule[$index] ?? end($backoffSchedule);
    }

    /**
     * Execute multiple callbacks in parallel with retry logic.
     *
     * @param array<string, Closure> $callbacks Keyed array of callbacks to execute
     * @param array<int> $backoffSchedule
     * @return array<string, mixed>
     */
    protected function withConcurrentRetries(
        array $callbacks,
        array $backoffSchedule = [1, 3, 10]
    ): array {
        $results = [];

        foreach ($callbacks as $key => $callback) {
            $results[$key] = $this->withRetry(
                callback: $callback,
                backoffSchedule: $backoffSchedule,
                operation: $key
            );
        }

        return $results;
    }
}
