<?php

namespace App\ValueObjects;

use App\Enums\OrderBadgeType;
use JsonSerializable;

readonly class OrderBadge implements JsonSerializable
{
    public function __construct(
        public OrderBadgeType $type,
        public ?string $customLabel = null,
        public ?array $metadata = null,
    ) {}

    public function label(): string
    {
        return $this->customLabel ?? $this->type->label();
    }

    public function color(): string
    {
        return $this->type->color();
    }

    public function icon(): string
    {
        return $this->type->icon();
    }

    public function description(): string
    {
        return $this->type->description();
    }

    public function priority(): int
    {
        return $this->type->priority();
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->label(),
            'color' => $this->color(),
            'icon' => $this->icon(),
            'description' => $this->description(),
            'priority' => $this->priority(),
            'metadata' => $this->metadata,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function equals(OrderBadge $other): bool
    {
        return $this->type === $other->type;
    }

    public function hasMetadata(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
