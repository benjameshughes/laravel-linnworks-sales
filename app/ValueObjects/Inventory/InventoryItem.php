<?php

declare(strict_types=1);

namespace App\ValueObjects\Inventory;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Value object representing an inventory item for batch operations.
 *
 * Uses PHP 8.2+ readonly and constructor property promotion.
 */
readonly class InventoryItem implements Arrayable
{
    public function __construct(
        public string $sku,
        public string $title,
        public ?string $barcode = null,
        public ?float $purchasePrice = null,
        public ?float $retailPrice = null,
        public ?int $stockLevel = null,
        public ?string $categoryName = null,
        public ?float $weight = null,
        public ?float $height = null,
        public ?float $width = null,
        public ?float $depth = null,
        public ?string $description = null,
        public ?array $extendedProperties = null,
    ) {}

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'] ?? $data['SKU'] ?? throw new \InvalidArgumentException('SKU is required'),
            title: $data['title'] ?? $data['Title'] ?? throw new \InvalidArgumentException('Title is required'),
            barcode: $data['barcode'] ?? $data['Barcode'] ?? null,
            purchasePrice: isset($data['purchase_price']) ? (float) $data['purchase_price'] :
                          (isset($data['PurchasePrice']) ? (float) $data['PurchasePrice'] : null),
            retailPrice: isset($data['retail_price']) ? (float) $data['retail_price'] :
                        (isset($data['RetailPrice']) ? (float) $data['RetailPrice'] : null),
            stockLevel: isset($data['stock_level']) ? (int) $data['stock_level'] :
                       (isset($data['StockLevel']) ? (int) $data['StockLevel'] : null),
            categoryName: $data['category_name'] ?? $data['CategoryName'] ?? null,
            weight: isset($data['weight']) ? (float) $data['weight'] :
                   (isset($data['Weight']) ? (float) $data['Weight'] : null),
            height: isset($data['height']) ? (float) $data['height'] :
                   (isset($data['Height']) ? (float) $data['Height'] : null),
            width: isset($data['width']) ? (float) $data['width'] :
                  (isset($data['Width']) ? (float) $data['Width'] : null),
            depth: isset($data['depth']) ? (float) $data['depth'] :
                  (isset($data['Depth']) ? (float) $data['Depth'] : null),
            description: $data['description'] ?? $data['Description'] ?? null,
            extendedProperties: $data['extended_properties'] ?? $data['ExtendedProperties'] ?? null,
        );
    }

    /**
     * Validate the inventory item data.
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->sku)) {
            $errors[] = 'SKU cannot be empty';
        }

        if (strlen($this->sku) > 100) {
            $errors[] = 'SKU cannot exceed 100 characters';
        }

        if (empty($this->title)) {
            $errors[] = 'Title cannot be empty';
        }

        if ($this->purchasePrice !== null && $this->purchasePrice < 0) {
            $errors[] = 'Purchase price cannot be negative';
        }

        if ($this->retailPrice !== null && $this->retailPrice < 0) {
            $errors[] = 'Retail price cannot be negative';
        }

        if ($this->stockLevel !== null && $this->stockLevel < 0) {
            $errors[] = 'Stock level cannot be negative';
        }

        if ($this->weight !== null && $this->weight < 0) {
            $errors[] = 'Weight cannot be negative';
        }

        return $errors;
    }

    /**
     * Check if item is valid.
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Convert to Linnworks API format.
     */
    public function toApiFormat(): array
    {
        $data = [
            'SKU' => $this->sku,
            'ItemTitle' => $this->title,
        ];

        if ($this->barcode !== null) {
            $data['BarcodeNumber'] = $this->barcode;
        }

        if ($this->purchasePrice !== null) {
            $data['PurchasePrice'] = $this->purchasePrice;
        }

        if ($this->retailPrice !== null) {
            $data['RetailPrice'] = $this->retailPrice;
        }

        if ($this->stockLevel !== null) {
            $data['Quantity'] = $this->stockLevel;
        }

        if ($this->categoryName !== null) {
            $data['CategoryName'] = $this->categoryName;
        }

        if ($this->weight !== null) {
            $data['Weight'] = $this->weight;
        }

        if ($this->height !== null) {
            $data['Height'] = $this->height;
        }

        if ($this->width !== null) {
            $data['Width'] = $this->width;
        }

        if ($this->depth !== null) {
            $data['Depth'] = $this->depth;
        }

        if ($this->description !== null) {
            $data['Description'] = $this->description;
        }

        if ($this->extendedProperties !== null) {
            $data['ExtendedProperties'] = $this->extendedProperties;
        }

        return $data;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'title' => $this->title,
            'barcode' => $this->barcode,
            'purchase_price' => $this->purchasePrice,
            'retail_price' => $this->retailPrice,
            'stock_level' => $this->stockLevel,
            'category_name' => $this->categoryName,
            'weight' => $this->weight,
            'height' => $this->height,
            'width' => $this->width,
            'depth' => $this->depth,
            'description' => $this->description,
            'extended_properties' => $this->extendedProperties,
        ];
    }
}
