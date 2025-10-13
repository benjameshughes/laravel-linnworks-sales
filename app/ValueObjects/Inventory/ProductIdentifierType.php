<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

/**
 * Product identifier type enum.
 *
 * Common identifier types used in retail and e-commerce.
 */
enum ProductIdentifierType: string
{
    case GTIN = 'GTIN';
    case EAN = 'EAN';
    case UPC = 'UPC';
    case ISBN = 'ISBN';
    case ASIN = 'ASIN';
    case BARCODE = 'BARCODE';
    case MPN = 'MPN'; // Manufacturer Part Number
    case CUSTOM = 'CUSTOM';

    /**
     * Create from string value (case-insensitive).
     */
    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }

        // Fuzzy matching
        return match (true) {
            str_contains($normalized, 'GTIN') => self::GTIN,
            str_contains($normalized, 'EAN') => self::EAN,
            str_contains($normalized, 'UPC') => self::UPC,
            str_contains($normalized, 'ISBN') => self::ISBN,
            str_contains($normalized, 'ASIN') => self::ASIN,
            str_contains($normalized, 'BARCODE') => self::BARCODE,
            str_contains($normalized, 'MPN') => self::MPN,
            default => self::CUSTOM,
        };
    }

    /**
     * Get human-readable description.
     */
    public function description(): string
    {
        return match ($this) {
            self::GTIN => 'Global Trade Item Number',
            self::EAN => 'European Article Number',
            self::UPC => 'Universal Product Code',
            self::ISBN => 'International Standard Book Number',
            self::ASIN => 'Amazon Standard Identification Number',
            self::BARCODE => 'Generic Barcode',
            self::MPN => 'Manufacturer Part Number',
            self::CUSTOM => 'Custom Identifier',
        };
    }

    /**
     * Check if identifier type is globally unique.
     */
    public function isGloballyUnique(): bool
    {
        return match ($this) {
            self::GTIN,
            self::EAN,
            self::UPC,
            self::ISBN,
            self::ASIN => true,
            default => false,
        };
    }

    /**
     * Get expected length for validation.
     */
    public function expectedLength(): ?int
    {
        return match ($this) {
            self::EAN => 13,
            self::UPC => 12,
            self::ISBN => 13,
            self::ASIN => 10,
            self::GTIN => null, // Variable: 8, 12, 13, or 14 digits
            default => null,
        };
    }

    /**
     * Validate identifier value format.
     */
    public function validate(string $value): bool
    {
        // Remove spaces and dashes
        $cleanValue = preg_replace('/[\s\-]/', '', $value);

        return match ($this) {
            self::EAN => $this->validateNumeric($cleanValue, 13),
            self::UPC => $this->validateNumeric($cleanValue, 12),
            self::ISBN => $this->validateNumeric($cleanValue, 13) || $this->validateNumeric($cleanValue, 10),
            self::GTIN => $this->validateGTIN($cleanValue),
            self::ASIN => strlen($cleanValue) === 10 && ctype_alnum($cleanValue),
            default => ! empty($cleanValue),
        };
    }

    /**
     * Validate numeric identifier with specific length.
     */
    private function validateNumeric(string $value, int $length): bool
    {
        return strlen($value) === $length && ctype_digit($value);
    }

    /**
     * Validate GTIN (can be 8, 12, 13, or 14 digits).
     */
    private function validateGTIN(string $value): bool
    {
        $length = strlen($value);

        return in_array($length, [8, 12, 13, 14], true) && ctype_digit($value);
    }
}
