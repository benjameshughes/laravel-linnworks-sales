<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Immutable product identifier value object.
 *
 * Represents a single product identifier (GTIN, EAN, UPC, etc.).
 * Uses PHP 8.2+ readonly properties for complete immutability.
 */
readonly class ProductIdentifier implements Arrayable
{
    public function __construct(
        public ProductIdentifierType $type,
        public string $value,
        public ?string $source = null,
        public bool $isDefault = false,
    ) {}

    /**
     * Create from API response.
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            type: ProductIdentifierType::fromString($data['Type'] ?? 'CUSTOM'),
            value: trim($data['Value'] ?? ''),
            source: $data['Source'] ?? null,
            isDefault: $data['IsDefault'] ?? false,
        );
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] instanceof ProductIdentifierType
                ? $data['type']
                : ProductIdentifierType::fromString($data['type'] ?? 'CUSTOM'),
            value: trim($data['value'] ?? ''),
            source: $data['source'] ?? null,
            isDefault: $data['is_default'] ?? false,
        );
    }

    /**
     * Validate identifier value.
     */
    public function isValid(): bool
    {
        return $this->type->validate($this->value);
    }

    /**
     * Get validation errors.
     *
     * @return array<string>
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->value)) {
            $errors[] = 'Identifier value is required';
        }

        if (!$this->isValid()) {
            $errors[] = sprintf(
                'Invalid %s format for value: %s',
                $this->type->value,
                $this->value
            );
        }

        return $errors;
    }

    /**
     * Get clean value (no spaces or dashes).
     */
    public function cleanValue(): string
    {
        return preg_replace('/[\s\-]/', '', $this->value);
    }

    /**
     * Check if this is a globally unique identifier.
     */
    public function isGloballyUnique(): bool
    {
        return $this->type->isGloballyUnique();
    }

    /**
     * Convert to API format.
     */
    public function toApiFormat(): array
    {
        return array_filter([
            'Type' => $this->type->value,
            'Value' => $this->value,
            'Source' => $this->source,
            'IsDefault' => $this->isDefault,
        ], fn ($value) => $value !== null);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'type_name' => $this->type->name,
            'type_description' => $this->type->description(),
            'value' => $this->value,
            'clean_value' => $this->cleanValue(),
            'source' => $this->source,
            'is_default' => $this->isDefault,
            'is_globally_unique' => $this->isGloballyUnique(),
            'is_valid' => $this->isValid(),
        ];
    }

    /**
     * Convert to string for display.
     */
    public function toString(): string
    {
        return sprintf(
            '%s: %s%s',
            $this->type->value,
            $this->value,
            $this->isDefault ? ' (default)' : ''
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
