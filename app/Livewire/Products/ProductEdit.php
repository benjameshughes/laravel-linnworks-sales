<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Edit Product')]
final class ProductEdit extends Component
{
    public Product $product;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:5000')]
    public ?string $description = null;

    #[Validate('nullable|string|max:255')]
    public ?string $brand = null;

    #[Validate('nullable|string|max:255')]
    public ?string $category_name = null;

    #[Validate('nullable|numeric|min:0|max:999999.9999')]
    public ?string $purchase_price = null;

    #[Validate('nullable|numeric|min:0|max:999999.9999')]
    public ?string $retail_price = null;

    #[Validate('nullable|numeric|min:0|max:999999.9999')]
    public ?string $shipping_cost = null;

    #[Validate('nullable|numeric|min:0|max:100')]
    public ?string $default_tax_rate = null;

    #[Validate('nullable|numeric|min:0|max:9999.999')]
    public ?string $weight = null;

    #[Validate('nullable|numeric|min:0|max:9999')]
    public ?string $dimension_height = null;

    #[Validate('nullable|numeric|min:0|max:9999')]
    public ?string $dimension_width = null;

    #[Validate('nullable|numeric|min:0|max:9999')]
    public ?string $dimension_depth = null;

    #[Validate('nullable|string|max:255')]
    public ?string $barcode = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $stock_minimum = null;

    #[Validate('boolean')]
    public bool $is_active = true;

    public function mount(string $sku): void
    {
        $this->product = Product::where('sku', $sku)->firstOrFail();

        $this->title = $this->product->title ?? '';
        $this->description = $this->product->description;
        $this->brand = $this->product->brand;
        $this->category_name = $this->product->category_name;
        $this->purchase_price = $this->product->purchase_price !== null ? (string) $this->product->purchase_price : null;
        $this->retail_price = $this->product->retail_price !== null ? (string) $this->product->retail_price : null;
        $this->shipping_cost = $this->product->shipping_cost !== null ? (string) $this->product->shipping_cost : null;
        $this->default_tax_rate = $this->product->default_tax_rate !== null ? (string) $this->product->default_tax_rate : null;
        $this->weight = $this->product->weight !== null ? (string) $this->product->weight : null;
        $this->barcode = $this->product->barcode;
        $this->stock_minimum = $this->product->stock_minimum;
        $this->is_active = $this->product->is_active;

        $dimensions = $this->product->dimensions ?? [];
        $this->dimension_height = isset($dimensions['height']) ? (string) $dimensions['height'] : null;
        $this->dimension_width = isset($dimensions['width']) ? (string) $dimensions['width'] : null;
        $this->dimension_depth = isset($dimensions['depth']) ? (string) $dimensions['depth'] : null;
    }

    public function save(): void
    {
        $this->validate();

        $this->product->update([
            'title' => $this->title,
            'description' => $this->description,
            'brand' => $this->brand,
            'category_name' => $this->category_name,
            'purchase_price' => $this->purchase_price !== null && $this->purchase_price !== '' ? (float) $this->purchase_price : null,
            'retail_price' => $this->retail_price !== null && $this->retail_price !== '' ? (float) $this->retail_price : null,
            'shipping_cost' => $this->shipping_cost !== null && $this->shipping_cost !== '' ? (float) $this->shipping_cost : null,
            'default_tax_rate' => $this->default_tax_rate !== null && $this->default_tax_rate !== '' ? (float) $this->default_tax_rate : null,
            'weight' => $this->weight !== null && $this->weight !== '' ? (float) $this->weight : null,
            'dimensions' => [
                'height' => $this->dimension_height !== null && $this->dimension_height !== '' ? (float) $this->dimension_height : null,
                'width' => $this->dimension_width !== null && $this->dimension_width !== '' ? (float) $this->dimension_width : null,
                'depth' => $this->dimension_depth !== null && $this->dimension_depth !== '' ? (float) $this->dimension_depth : null,
            ],
            'barcode' => $this->barcode,
            'stock_minimum' => $this->stock_minimum,
            'is_active' => $this->is_active,
        ]);

        $this->dispatch('product-updated');
    }

    public function render()
    {
        return view('livewire.products.product-edit');
    }
}
