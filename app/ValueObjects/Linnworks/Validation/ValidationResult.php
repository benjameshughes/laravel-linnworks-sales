<?php

declare(strict_types=1);

namespace App\ValueObjects\Linnworks\Validation;

use JsonSerializable;

/**
 * Immutable value object representing validation result
 */
final readonly class ValidationResult implements JsonSerializable
{
    /**
     * @param  array<string, array<string>>  $errors  Keyed by field name, values are arrays of error messages
     * @param  array<string, mixed>  $warnings  Non-fatal validation warnings
     * @param  array<string, mixed>  $metadata  Additional validation metadata
     */
    private function __construct(
        public bool $isValid,
        public array $errors,
        public array $warnings,
        public array $metadata
    ) {}

    /**
     * Create a successful validation result
     */
    public static function success(array $metadata = []): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: [],
            metadata: $metadata
        );
    }

    /**
     * Create a failed validation result
     *
     * @param  array<string, string|array<string>>  $errors
     */
    public static function failed(array $errors, array $warnings = [], array $metadata = []): self
    {
        // Normalize errors to always be arrays
        $normalizedErrors = [];
        foreach ($errors as $field => $error) {
            $normalizedErrors[$field] = is_array($error) ? $error : [$error];
        }

        return new self(
            isValid: false,
            errors: $normalizedErrors,
            warnings: $warnings,
            metadata: $metadata
        );
    }

    /**
     * Create a result with warnings but still valid
     */
    public static function withWarnings(array $warnings, array $metadata = []): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings,
            metadata: $metadata
        );
    }

    /**
     * Check if validation failed
     */
    public function hasFailed(): bool
    {
        return ! $this->isValid;
    }

    /**
     * Check if validation passed
     */
    public function passed(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if there are warnings
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get all error messages as flat array
     *
     * @return array<string>
     */
    public function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        return $messages;
    }

    /**
     * Get errors for a specific field
     *
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->errors[$field]) && ! empty($this->errors[$field]);
    }

    /**
     * Get first error message (useful for simple validation)
     */
    public function getFirstError(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }

        $firstField = array_key_first($this->errors);
        $fieldErrors = $this->errors[$firstField];

        return ! empty($fieldErrors) ? $fieldErrors[0] : null;
    }

    /**
     * Merge with another validation result
     */
    public function merge(ValidationResult $other): self
    {
        return new self(
            isValid: $this->isValid && $other->isValid,
            errors: array_merge_recursive($this->errors, $other->errors),
            warnings: array_merge($this->warnings, $other->warnings),
            metadata: array_merge($this->metadata, $other->metadata)
        );
    }

    /**
     * Add metadata to the result
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            isValid: $this->isValid,
            errors: $this->errors,
            warnings: $this->warnings,
            metadata: array_merge($this->metadata, $metadata)
        );
    }

    /**
     * Get count of errors
     */
    public function errorCount(): int
    {
        return array_sum(array_map('count', $this->errors));
    }

    /**
     * Convert to array for logging
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'error_count' => $this->errorCount(),
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
        if ($this->isValid) {
            return $this->hasWarnings()
                ? 'Valid with '.count($this->warnings).' warning(s)'
                : 'Valid';
        }

        return 'Invalid: '.implode(', ', $this->getErrorMessages());
    }
}
