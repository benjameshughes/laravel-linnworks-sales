<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Core;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker implementation for Linnworks API
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Too many failures, requests are blocked
 * - HALF_OPEN: Testing if service has recovered
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 60, // seconds
        private readonly int $successThreshold = 2, // successes needed in half-open to close
    ) {}

    /**
     * Check if requests can be made through the circuit
     */
    public function canMakeRequest(): bool
    {
        $state = $this->getState();

        return match ($state) {
            self::STATE_CLOSED => true,
            self::STATE_OPEN => $this->shouldAttemptRecovery(),
            self::STATE_HALF_OPEN => true,
            default => true,
        };
    }

    /**
     * Record a successful request
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = $this->incrementSuccessCount();

            if ($successes >= $this->successThreshold) {
                $this->transitionTo(self::STATE_CLOSED);
                Log::info('Circuit breaker closed', [
                    'service' => $this->serviceName,
                    'successes' => $successes,
                ]);
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure count on success
            $this->resetFailureCount();
        }
    }

    /**
     * Record a failed request
     */
    public function recordFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Failure during recovery, reopen circuit
            $this->transitionTo(self::STATE_OPEN);
            Log::warning('Circuit breaker reopened after recovery failure', [
                'service' => $this->serviceName,
            ]);

            return;
        }

        if ($state === self::STATE_CLOSED) {
            $failures = $this->incrementFailureCount();

            if ($failures >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
                Log::error('Circuit breaker opened due to failures', [
                    'service' => $this->serviceName,
                    'failures' => $failures,
                    'threshold' => $this->failureThreshold,
                ]);
            }
        }
    }

    /**
     * Get current circuit state
     */
    public function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    /**
     * Get circuit breaker statistics
     */
    public function getStats(): array
    {
        return [
            'service' => $this->serviceName,
            'state' => $this->getState(),
            'failures' => $this->getFailureCount(),
            'successes' => $this->getSuccessCount(),
            'failure_threshold' => $this->failureThreshold,
            'success_threshold' => $this->successThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'last_failure_at' => Cache::get($this->getLastFailureKey()),
        ];
    }

    /**
     * Manually reset the circuit breaker
     */
    public function reset(): void
    {
        Cache::forget($this->getStateKey());
        Cache::forget($this->getFailureCountKey());
        Cache::forget($this->getSuccessCountKey());
        Cache::forget($this->getLastFailureKey());

        Log::info('Circuit breaker manually reset', [
            'service' => $this->serviceName,
        ]);
    }

    /**
     * Check if enough time has passed to attempt recovery
     */
    private function shouldAttemptRecovery(): bool
    {
        $lastFailure = Cache::get($this->getLastFailureKey());

        if (! $lastFailure) {
            return true;
        }

        $timeSinceFailure = now()->diffInSeconds($lastFailure);

        if ($timeSinceFailure >= $this->recoveryTimeout) {
            $this->transitionTo(self::STATE_HALF_OPEN);
            Log::info('Circuit breaker transitioning to half-open', [
                'service' => $this->serviceName,
                'time_since_failure' => $timeSinceFailure,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Transition to a new state
     */
    private function transitionTo(string $newState): void
    {
        Cache::put($this->getStateKey(), $newState, now()->addHours(24));

        // Reset counters based on new state
        if ($newState === self::STATE_CLOSED) {
            $this->resetFailureCount();
            $this->resetSuccessCount();
            Cache::forget($this->getLastFailureKey());
        } elseif ($newState === self::STATE_HALF_OPEN) {
            $this->resetSuccessCount();
        }
    }

    /**
     * Increment failure count
     */
    private function incrementFailureCount(): int
    {
        $key = $this->getFailureCountKey();
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addHours(24));
        Cache::put($this->getLastFailureKey(), now(), now()->addHours(24));

        return $count;
    }

    /**
     * Increment success count
     */
    private function incrementSuccessCount(): int
    {
        $key = $this->getSuccessCountKey();
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addHours(24));

        return $count;
    }

    /**
     * Reset failure count
     */
    private function resetFailureCount(): void
    {
        Cache::forget($this->getFailureCountKey());
    }

    /**
     * Reset success count
     */
    private function resetSuccessCount(): void
    {
        Cache::forget($this->getSuccessCountKey());
    }

    /**
     * Get current failure count
     */
    private function getFailureCount(): int
    {
        return (int) Cache::get($this->getFailureCountKey(), 0);
    }

    /**
     * Get current success count
     */
    private function getSuccessCount(): int
    {
        return (int) Cache::get($this->getSuccessCountKey(), 0);
    }

    private function getStateKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:state";
    }

    private function getFailureCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:failures";
    }

    private function getSuccessCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:successes";
    }

    private function getLastFailureKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:last_failure";
    }
}
