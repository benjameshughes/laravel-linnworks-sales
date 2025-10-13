<?php

namespace App\Enums;

enum SearchType: string
{
    case TEXT = 'text';
    case SKU = 'sku';
    case CATEGORY = 'category';
    case BRAND = 'brand';
    case BARCODE = 'barcode';
    case COMBINED = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text Search',
            self::SKU => 'SKU Search',
            self::CATEGORY => 'Category Search',
            self::BRAND => 'Brand Search',
            self::BARCODE => 'Barcode Search',
            self::COMBINED => 'Multi-Criteria Search',
        };
    }

    public function getSearchFields(): array
    {
        return match ($this) {
            self::TEXT => ['title', 'description', 'searchable_content'],
            self::SKU => ['sku'],
            self::CATEGORY => ['category_name'],
            self::BRAND => ['brand'],
            self::BARCODE => ['barcode'],
            self::COMBINED => ['sku', 'title', 'description', 'category_name', 'brand', 'barcode', 'searchable_content'],
        };
    }

    public function getPlaceholder(): string
    {
        return match ($this) {
            self::TEXT => 'Search by product name or description...',
            self::SKU => 'Enter SKU...',
            self::CATEGORY => 'Search by category...',
            self::BRAND => 'Search by brand...',
            self::BARCODE => 'Enter barcode...',
            self::COMBINED => 'Search products, SKUs, categories...',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::TEXT => 'magnifying-glass',
            self::SKU => 'hashtag',
            self::CATEGORY => 'tag',
            self::BRAND => 'building-storefront',
            self::BARCODE => 'qr-code',
            self::COMBINED => 'squares-plus',
        };
    }

    public function supportsFuzzySearch(): bool
    {
        return match ($this) {
            self::TEXT, self::COMBINED => true,
            default => false,
        };
    }

    public function supportsWildcards(): bool
    {
        return match ($this) {
            self::SKU, self::BARCODE => true,
            default => false,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::TEXT => 'Search through product titles and descriptions using natural language.',
            self::SKU => 'Find products by their exact SKU codes or partial matches.',
            self::CATEGORY => 'Search within specific product categories.',
            self::BRAND => 'Filter products by brand or manufacturer.',
            self::BARCODE => 'Locate products using barcode or EAN numbers.',
            self::COMBINED => 'Search across all fields simultaneously for comprehensive results.',
        };
    }
}
