<?php

namespace App\ValueObjects;

use Carbon\Carbon;
use JsonSerializable;

readonly class DateRange implements JsonSerializable
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
    ) {}

    public static function fromPeriod(int $days): self
    {
        return new self(
            from: now()->subDays($days),
            to: now(),
        );
    }

    public static function fromDates(Carbon $from, Carbon $to): self
    {
        return new self(
            from: $from,
            to: $to,
        );
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to);
    }

    public function hours(): int
    {
        return (int) $this->from->diffInHours($this->to);
    }

    public function contains(Carbon $date): bool
    {
        return $date->between($this->from, $this->to);
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->from->lt($other->to) && $this->to->gt($other->from);
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->toISOString(),
            'to' => $this->to->toISOString(),
            'days' => $this->days(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return "{$this->from->format('Y-m-d')} to {$this->to->format('Y-m-d')}";
    }
}
