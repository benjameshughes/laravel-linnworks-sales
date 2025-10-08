<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

use Illuminate\Support\Collection;

/**
 * Specialized collection for product identifiers.
 *
 * Extends Laravel Collection with domain-specific methods.
 */
class ProductIdentifierCollection extends Collection
{
    /**
     * Create from API response.
     */
    public static function fromApiResponse(array $data): self
    {
        $identifiers = collect($data['ProductIdentifiers'] ?? $data)
            ->map(fn (array $item) => ProductIdentifier::fromApiResponse($item));

        return new self($identifiers);
    }

    /**
     * Get identifiers of a specific type.
     */
    public function ofType(ProductIdentifierType $type): self
    {
        return $this->filter(fn (ProductIdentifier $id) => $id->type === $type);
    }

    /**
     * Get the default identifier.
     */
    public function default(): ?ProductIdentifier
    {
        return $this->firstWhere('isDefault', true);
    }

    /**
     * Get all globally unique identifiers.
     */
    public function globallyUnique(): self
    {
        return $this->filter(fn (ProductIdentifier $id) => $id->isGloballyUnique());
    }

    /**
     * Get all valid identifiers.
     */
    public function valid(): self
    {
        return $this->filter(fn (ProductIdentifier $id) => $id->isValid());
    }

    /**
     * Get all invalid identifiers.
     */
    public function invalid(): self
    {
        return $this->filter(fn (ProductIdentifier $id) => !$id->isValid());
    }

    /**
     * Find identifier by value.
     */
    public function findByValue(string $value): ?ProductIdentifier
    {
        $cleanValue = preg_replace('/[\s\-]/', '', $value);

        return $this->first(function (ProductIdentifier $id) use ($cleanValue) {
            return $id->cleanValue() === $cleanValue;
        });
    }

    /**
     * Check if collection has a specific identifier type.
     */
    public function hasType(ProductIdentifierType $type): bool
    {
        return $this->ofType($type)->isNotEmpty();
    }

    /**
     * Get GTIN identifier.
     */
    public function gtin(): ?ProductIdentifier
    {
        return $this->ofType(ProductIdentifierType::GTIN)->first();
    }

    /**
     * Get EAN identifier.
     */
    public function ean(): ?ProductIdentifier
    {
        return $this->ofType(ProductIdentifierType::EAN)->first();
    }

    /**
     * Get UPC identifier.
     */
    public function upc(): ?ProductIdentifier
    {
        return $this->ofType(ProductIdentifierType::UPC)->first();
    }

    /**
     * Get ASIN identifier.
     */
    public function asin(): ?ProductIdentifier
    {
        return $this->ofType(ProductIdentifierType::ASIN)->first();
    }

    /**
     * Get barcode identifier.
     */
    public function barcode(): ?ProductIdentifier
    {
        return $this->ofType(ProductIdentifierType::BARCODE)->first();
    }

    /**
     * Get MPN identifier.
     */
    public function mpn(): ?ProductIdentifier
    {
        return $this->ofType(ProductIdentifierType::MPN)->first();
    }

    /**
     * Group by identifier type.
     *
     * @return Collection<string, self>
     */
    public function groupByType(): Collection
    {
        return $this->groupBy(fn (ProductIdentifier $id) => $id->type->value)
            ->map(fn (Collection $ids) => new self($ids));
    }

    /**
     * Get validation errors for all identifiers.
     */
    public function validationErrors(): array
    {
        $errors = [];

        foreach ($this as $index => $identifier) {
            $idErrors = $identifier->validate();
            if (!empty($idErrors)) {
                $errors["identifier_{$index}"] = $idErrors;
            }
        }

        return $errors;
    }

    /**
     * Get statistics about the collection.
     */
    public function statistics(): array
    {
        return [
            'total' => $this->count(),
            'valid' => $this->valid()->count(),
            'invalid' => $this->invalid()->count(),
            'globally_unique' => $this->globallyUnique()->count(),
            'has_default' => $this->default() !== null,
            'types' => $this->groupByType()->map(fn ($ids) => $ids->count())->toArray(),
        ];
    }

    /**
     * Convert all identifiers to API format.
     */
    public function toApiFormat(): array
    {
        return $this->map(fn (ProductIdentifier $id) => $id->toApiFormat())->toArray();
    }
}
