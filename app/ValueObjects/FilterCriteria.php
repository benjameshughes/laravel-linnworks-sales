<?php

namespace App\ValueObjects;

use App\Enums\ProductFilterType;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class FilterCriteria implements JsonSerializable
{
    public function __construct(
        public ProductFilterType $type,
        public mixed $value,
        public ?string $operator = null,
        public ?array $metadata = null,
    ) {}

    public function isActive(): bool
    {
        return ! $this->isEmpty();
    }

    public function label(): string
    {
        return $this->type->label();
    }

    public function icon(): string
    {
        return $this->type->getIcon();
    }

    public function options(): array
    {
        return $this->type->getOptions();
    }

    public function allowsMultiple(): bool
    {
        return $this->type->allowsMultipleSelection();
    }

    public function isEmpty(): bool
    {
        return match (true) {
            is_null($this->value) => true,
            is_string($this->value) => trim($this->value) === '',
            is_array($this->value) => empty($this->value),
            $this->value instanceof Collection => $this->value->isEmpty(),
            default => false,
        };
    }

    public function matches(array $productData): bool
    {
        if ($this->isEmpty()) {
            return true;
        }

        return match ($this->type) {
            ProductFilterType::PROFIT_MARGIN => $this->matchesRange(
                $productData['profit_margin'] ?? 0,
                $this->options[$this->value] ?? []
            ),
            ProductFilterType::SALES_VELOCITY => $this->matchesRange(
                $productData['avg_daily_sales'] ?? 0,
                $this->options[$this->value] ?? []
            ),
            ProductFilterType::GROWTH_RATE => $this->matchesRange(
                $productData['growth_rate'] ?? 0,
                $this->options[$this->value] ?? []
            ),
            ProductFilterType::REVENUE_TIER => $this->matchesRange(
                $productData['total_revenue'] ?? 0,
                $this->options[$this->value] ?? []
            ),
            ProductFilterType::PRODUCT_AGE => $this->matchesAge(
                $productData['created_at'] ?? null,
                $this->options[$this->value] ?? []
            ),
            ProductFilterType::BADGE_TYPE => $this->matchesBadge(
                $productData['badges'] ?? collect(),
                $this->value
            ),
            ProductFilterType::CATEGORY => $this->matchesCategory(
                $productData['category'] ?? null,
                $this->value
            ),
            ProductFilterType::STOCK_STATUS => $this->matchesStock(
                $productData,
                $this->options[$this->value] ?? []
            ),
            ProductFilterType::PERFORMANCE_SCORE => $this->matchesRange(
                $productData['performance_score'] ?? 0,
                $this->options[$this->value] ?? []
            ),
        };
    }

    private function matchesRange(float|int $value, array $range): bool
    {
        if (empty($range)) {
            return true;
        }

        $min = $range['min'] ?? null;
        $max = $range['max'] ?? null;

        return match (true) {
            is_null($min) && is_null($max) => true,
            is_null($min) => $value <= $max,
            is_null($max) => $value >= $min,
            default => $value >= $min && $value <= $max,
        };
    }

    private function matchesAge(?string $createdAt, array $range): bool
    {
        if (! $createdAt || empty($range)) {
            return true;
        }

        $daysOld = now()->diffInDays($createdAt);

        return $this->matchesRange($daysOld, $range);
    }

    private function matchesBadge(Collection $badges, mixed $badgeValue): bool
    {
        if ($badges->isEmpty()) {
            return false;
        }

        $badgeTypes = is_array($badgeValue) ? $badgeValue : [$badgeValue];

        return $badges->pluck('type')
            ->intersect($badgeTypes)
            ->isNotEmpty();
    }

    private function matchesCategory(?string $category, mixed $categoryValue): bool
    {
        if (! $category) {
            return false;
        }

        $categories = is_array($categoryValue) ? $categoryValue : [$categoryValue];

        return in_array($category, $categories, true);
    }

    private function matchesStock(array $productData, array $condition): bool
    {
        if (empty($condition)) {
            return true;
        }

        $field = $condition['condition'] ?? 'stock_level';
        $operator = $condition['operator'] ?? '=';
        $value = $condition['value'] ?? 0;
        $productValue = $productData[$field] ?? 0;

        return match ($operator) {
            '=' => $productValue == $value,
            '!=' => $productValue != $value,
            '>' => $productValue > $value,
            '>=' => $productValue >= $value,
            '<' => $productValue < $value,
            '<=' => $productValue <= $value,
            default => false,
        };
    }

    public function getDisplayValue(): string
    {
        if ($this->isEmpty()) {
            return 'All';
        }

        return match ($this->type) {
            ProductFilterType::BADGE_TYPE => is_array($this->value)
                ? count($this->value).' badges selected'
                : ($this->options[$this->value]['label'] ?? $this->value),
            ProductFilterType::CATEGORY => is_array($this->value)
                ? count($this->value).' categories selected'
                : $this->value,
            default => $this->options[$this->value]['label'] ?? $this->value,
        };
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->value,
            'operator' => $this->operator,
            'metadata' => $this->metadata,
            'is_active' => $this->isActive(),
            'label' => $this->label(),
            'display_value' => $this->getDisplayValue(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function withValue(mixed $value): self
    {
        return new self(
            type: $this->type,
            value: $value,
            operator: $this->operator,
            metadata: $this->metadata,
        );
    }

    public function withOperator(string $operator): self
    {
        return new self(
            type: $this->type,
            value: $this->value,
            operator: $operator,
            metadata: $this->metadata,
        );
    }

    public function withMetadata(array $metadata): self
    {
        return new self(
            type: $this->type,
            value: $this->value,
            operator: $this->operator,
            metadata: array_merge($this->metadata ?? [], $metadata),
        );
    }
}
