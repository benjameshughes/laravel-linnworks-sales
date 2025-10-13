<?php

declare(strict_types=1);

namespace App\Exceptions\Linnworks;

use Exception;
use Illuminate\Http\Client\Response;
use Psr\Log\LogLevel;

class LinnworksApiException extends Exception
{
    public function __construct(
        string $message,
        protected readonly ?Response $response = null,
        protected readonly ?array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(Response $response, string $message = 'Linnworks API request failed'): self
    {
        return new self(
            message: $message,
            response: $response,
            context: [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ],
            code: $response->status()
        );
    }

    public static function rateLimited(Response $response): self
    {
        $retryAfter = $response->header('Retry-After');

        return new self(
            message: 'Linnworks API rate limit exceeded',
            response: $response,
            context: [
                'status' => $response->status(),
                'retry_after' => $retryAfter,
                'body' => $response->body(),
            ],
            code: 429
        );
    }

    public static function timeout(string $endpoint, float $duration): self
    {
        return new self(
            message: "Linnworks API request to {$endpoint} timed out after {$duration}s",
            context: [
                'endpoint' => $endpoint,
                'duration' => $duration,
            ],
            code: 408
        );
    }

    public static function invalidResponse(string $reason, ?Response $response = null): self
    {
        return new self(
            message: "Invalid Linnworks API response: {$reason}",
            response: $response,
            context: [
                'reason' => $reason,
                'body' => $response?->body(),
            ],
            code: 500
        );
    }

    public function isRateLimited(): bool
    {
        return $this->code === 429;
    }

    public function isTimeout(): bool
    {
        return $this->code === 408;
    }

    public function isServerError(): bool
    {
        return $this->code >= 500 && $this->code < 600;
    }

    public function isClientError(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    public function isRetryable(): bool
    {
        return match (true) {
            $this->isRateLimited() => true,
            $this->isTimeout() => true,
            $this->isServerError() => true,
            $this->code === 503 => true, // Service unavailable
            $this->code === 502 => true, // Bad gateway
            default => false,
        };
    }

    public function getRetryAfter(): ?int
    {
        if (! $this->isRateLimited()) {
            return null;
        }

        return isset($this->context['retry_after'])
            ? (int) $this->context['retry_after']
            : null;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function context(): array
    {
        return array_merge($this->context, [
            'exception_class' => static::class,
            'is_retryable' => $this->isRetryable(),
            'is_rate_limited' => $this->isRateLimited(),
        ]);
    }

    public function report(): void
    {
        $level = match (true) {
            $this->isRateLimited() => LogLevel::WARNING,
            $this->isTimeout() => LogLevel::WARNING,
            $this->isServerError() => LogLevel::ERROR,
            default => LogLevel::ERROR,
        };

        logger()->log($level, $this->getMessage(), $this->context());
    }
}
