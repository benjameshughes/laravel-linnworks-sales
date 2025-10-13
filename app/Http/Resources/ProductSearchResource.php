<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSearchResource extends JsonResource
{
    public function __construct(
        private readonly Product $product,
        private readonly string $variant = 'basic'
    ) {
        parent::__construct($product);
    }

    public static function basic(Product $product): self
    {
        return new self($product, 'basic');
    }

    public static function detailed(Product $product): self
    {
        return new self($product, 'detailed');
    }

    public function toArray(Request $request): array
    {
        return match ($this->variant) {
            'basic' => $this->basicFormat(),
            'detailed' => $this->detailedFormat(),
            default => $this->basicFormat(),
        };
    }

    private function basicFormat(): array
    {
        return [
            'id' => $this->product->id,
            'sku' => $this->product->sku,
            'title' => $this->product->title,
            'category' => $this->product->category_name,
            'brand' => $this->product->brand,
            'price' => $this->product->retail_price,
            'stock_level' => $this->product->stock_level,
            'is_active' => $this->product->is_active,
            'url' => route('products.detail', $this->product->sku),
        ];
    }

    private function detailedFormat(): array
    {
        return [
            'id' => $this->product->id,
            'sku' => $this->product->sku,
            'title' => $this->product->title,
            'description' => $this->product->description,
            'category' => $this->product->category_name,
            'brand' => $this->product->brand,
            'price' => $this->product->retail_price,
            'purchase_price' => $this->product->purchase_price,
            'stock_level' => $this->product->stock_level,
            'stock_minimum' => $this->product->stock_minimum,
            'is_active' => $this->product->is_active,
            'barcode' => $this->product->barcode,
            'weight' => $this->product->weight,
            'dimensions' => $this->product->dimensions,
            'url' => route('products.detail', $this->product->sku),
            'created_at' => $this->product->created_at?->toISOString(),
            'updated_at' => $this->product->updated_at?->toISOString(),
        ];
    }
}
