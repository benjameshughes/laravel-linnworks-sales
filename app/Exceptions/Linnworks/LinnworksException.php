<?php

declare(strict_types=1);

namespace App\Exceptions\Linnworks;

use RuntimeException;

/**
 * Base exception for all Linnworks-related errors
 */
class LinnworksException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get exception context for logging
     */
    public function context(): array
    {
        return $this->context;
    }
}
