<?php

declare(strict_types=1);

namespace App\ValueObjects;

use JsonSerializable;
use App\Enums\GrowthDirection;

/**
 * Presentation-ready view of a period-over-period percentage change.
 *
 * Everything the badge needs - icon, tone and label - is resolved here so the
 * template only interpolates. A null percentage means the baseline period had
 * no volume, which is reported as unmeasurable rather than infinite growth.
 */
final readonly class GrowthIndicator implements JsonSerializable
{
    private const FLAT_THRESHOLD = 0.05;

    private const DECIMALS = 1;

    public function __construct(
        public GrowthDirection $direction,
        public ?float $percentage,
        public string $unmeasurableLabel = 'New',
    ) {}

    public static function fromPercentage(?float $percentage, string $unmeasurableLabel = 'New'): self
    {
        return new self(
            direction: self::resolveDirection($percentage),
            percentage: $percentage,
            unmeasurableLabel: $unmeasurableLabel,
        );
    }

    public function icon(): string
    {
        return $this->direction->icon();
    }

    public function toneClass(): string
    {
        return $this->direction->toneClass();
    }

    public function label(): string
    {
        if ($this->percentage === null) {
            return $this->unmeasurableLabel;
        }

        $sign = $this->direction === GrowthDirection::Up ? '+' : '';

        return $sign.number_format($this->percentage, self::DECIMALS).'%';
    }

    /**
     * @return array{direction: string, percentage: float|null, icon: string, tone: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction->value,
            'percentage' => $this->percentage,
            'icon' => $this->icon(),
            'tone' => $this->toneClass(),
            'label' => $this->label(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function resolveDirection(?float $percentage): GrowthDirection
    {
        return match (true) {
            $percentage === null => GrowthDirection::Unmeasurable,
            abs($percentage) < self::FLAT_THRESHOLD => GrowthDirection::Flat,
            $percentage > 0 => GrowthDirection::Up,
            default => GrowthDirection::Down,
        };
    }
}
