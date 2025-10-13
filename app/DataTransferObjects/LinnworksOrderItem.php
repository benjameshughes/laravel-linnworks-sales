<?php

namespace App\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class LinnworksOrderItem implements Arrayable
{
    public function __construct(
        public ?string $itemId,
        public ?string $sku,
        public ?string $itemTitle,
        public int $quantity,
        public float $unitCost,
        public float $pricePerUnit,
        public float $lineTotal,
        public ?string $categoryName,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            itemId: $data['ItemId'] ?? $data['item_id'] ?? null,
            sku: $data['SKU'] ?? $data['sku'] ?? null,
            itemTitle: $data['ItemTitle'] ?? $data['item_title'] ?? null,
            quantity: (int) ($data['Quantity'] ?? $data['quantity'] ?? 0),
            unitCost: (float) ($data['UnitCost'] ?? $data['unit_cost'] ?? 0),
            pricePerUnit: (float) ($data['PricePerUnit'] ?? $data['price_per_unit'] ?? 0),
            lineTotal: (float) ($data['LineTotal'] ?? $data['line_total'] ?? 0),
            categoryName: $data['CategoryName'] ?? $data['category_name'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'sku' => $this->sku,
            'item_title' => $this->itemTitle,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unitCost,
            'price_per_unit' => $this->pricePerUnit,
            'line_total' => $this->lineTotal,
            'category_name' => $this->categoryName,
        ];
    }

    public function totalValue(): float
    {
        return $this->quantity * $this->pricePerUnit;
    }

    public function profit(): float
    {
        return ($this->pricePerUnit - $this->unitCost) * $this->quantity;
    }

    public function profitMargin(): float
    {
        return $this->pricePerUnit > 0
            ? (($this->pricePerUnit - $this->unitCost) / $this->pricePerUnit) * 100
            : 0;
    }
}
