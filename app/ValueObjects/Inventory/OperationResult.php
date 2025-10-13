<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

/**
 * Immutable value object representing a single operation result within a batch.
 *
 * Uses PHP 8.2+ readonly properties.
 */
readonly class OperationResult
{
    public function __construct(
        public bool $success,
        public mixed $data,
        public ?string $errorMessage = null,
        public ?int $errorCode = null,
    ) {}

    /**
     * Create from Linnworks API result response.
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            success: $response['Success'] ?? false,
            data: $response['Data'] ?? null,
            errorMessage: $response['Error'] ?? null,
            errorCode: $response['ErrorCode'] ?? null,
        );
    }

    /**
     * Create a successful result.
     */
    public static function success(mixed $data): self
    {
        return new self(
            success: true,
            data: $data,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $errorMessage, ?int $errorCode = null): self
    {
        return new self(
            success: false,
            data: null,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
        );
    }

    /**
     * Check if operation succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if operation failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Get data or throw if failed.
     */
    public function getDataOrFail(): mixed
    {
        if ($this->isFailure()) {
            throw new \RuntimeException(
                $this->errorMessage ?? 'Operation failed',
                $this->errorCode ?? 0
            );
        }

        return $this->data;
    }

    /**
     * Get data or return default.
     */
    public function getDataOr(mixed $default): mixed
    {
        return $this->isSuccess() ? $this->data : $default;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
        ];
    }
}
