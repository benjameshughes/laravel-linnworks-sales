<?php

declare(strict_types=1);

namespace App\ValueObjects\Analytics;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use InvalidArgumentException;

readonly class DateRange
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {
        if ($this->start->isAfter($this->end)) {
            throw new InvalidArgumentException('Start date must be before or equal to end date');
        }
    }

    public static function fromDays(int $days): self
    {
        return new self(
            start: Carbon::now()->subDays($days)->startOfDay(),
            end: Carbon::now()->endOfDay(),
        );
    }

    public static function last7Days(): self
    {
        return self::fromDays(7);
    }

    public static function last30Days(): self
    {
        return self::fromDays(30);
    }

    public static function last90Days(): self
    {
        return self::fromDays(90);
    }

    public static function thisMonth(): self
    {
        return new self(
            start: Carbon::now()->startOfMonth(),
            end: Carbon::now()->endOfDay(),
        );
    }

    public static function lastMonth(): self
    {
        $lastMonth = Carbon::now()->subMonth();

        return new self(
            start: $lastMonth->startOfMonth(),
            end: $lastMonth->endOfMonth(),
        );
    }

    public static function yearToDate(): self
    {
        return new self(
            start: Carbon::now()->startOfYear(),
            end: Carbon::now()->endOfDay(),
        );
    }

    public static function custom(string $startDate, string $endDate): self
    {
        return new self(
            start: Carbon::parse($startDate)->startOfDay(),
            end: Carbon::parse($endDate)->endOfDay(),
        );
    }

    public static function fromPreset(string $preset): self
    {
        return match ($preset) {
            'last_7_days' => self::last7Days(),
            'last_30_days' => self::last30Days(),
            'last_90_days' => self::last90Days(),
            'this_month' => self::thisMonth(),
            'last_month' => self::lastMonth(),
            'year_to_date' => self::yearToDate(),
            default => self::last30Days(),
        };
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
        ];
    }

    public function diffInDays(): int
    {
        return (int) $this->start->diffInDays($this->end);
    }

    public function period(): CarbonPeriod
    {
        return CarbonPeriod::create($this->start, $this->end);
    }

    public function contains(Carbon $date): bool
    {
        return $date->between($this->start, $this->end);
    }

    public function getPreviousPeriod(): self
    {
        $days = $this->diffInDays();

        return new self(
            start: $this->start->copy()->subDays($days + 1),
            end: $this->start->copy()->subDay(),
        );
    }

    public function format(string $format = 'M j, Y'): string
    {
        return $this->start->format($format) . ' - ' . $this->end->format($format);
    }

    public function isToday(): bool
    {
        return $this->start->isToday() && $this->end->isToday();
    }

    public function isYesterday(): bool
    {
        return $this->start->isYesterday() && $this->end->isYesterday();
    }
}
