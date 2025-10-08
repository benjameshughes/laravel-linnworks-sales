<?php

declare(strict_types=1);

namespace App\ValueObjects\AuditTrail;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Specialized collection for audit events with filtering and aggregation.
 *
 * Extends Laravel Collection with domain-specific methods.
 */
class AuditTrailCollection extends Collection
{
    /**
     * Create from array of audit data.
     */
    public static function fromApiResponse(array $data, string $sourceType = 'inventory'): self
    {
        $events = collect($data)->map(function ($item) use ($sourceType) {
            return match ($sourceType) {
                'inventory' => AuditEvent::fromInventoryAudit($item),
                'order' => AuditEvent::fromOrderAudit($item),
                default => AuditEvent::fromInventoryAudit($item),
            };
        });

        return new self($events);
    }

    /**
     * Filter by event type.
     */
    public function ofType(AuditEventType $type): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->type === $type);
    }

    /**
     * Filter by multiple event types.
     *
     * @param  array<AuditEventType>  $types
     */
    public function ofTypes(array $types): self
    {
        return $this->filter(fn (AuditEvent $event) => in_array($event->type, $types, true));
    }

    /**
     * Get only critical events.
     */
    public function critical(): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->isCritical());
    }

    /**
     * Get only informational events.
     */
    public function informational(): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->isInformational());
    }

    /**
     * Filter by date range.
     */
    public function betweenDates(Carbon $from, Carbon $to): self
    {
        return $this->filter(
            fn (AuditEvent $event) => $event->occurredAt->between($from, $to)
        );
    }

    /**
     * Filter by user.
     */
    public function byUser(string $userName): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->userName === $userName);
    }

    /**
     * Filter by resource ID.
     */
    public function forResource(string $resourceId): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->resourceId === $resourceId);
    }

    /**
     * Get events from last N hours.
     */
    public function lastHours(int $hours): self
    {
        $since = now()->subHours($hours);

        return $this->filter(fn (AuditEvent $event) => $event->occurredAt->gte($since));
    }

    /**
     * Get events from last N days.
     */
    public function lastDays(int $days): self
    {
        $since = now()->subDays($days);

        return $this->filter(fn (AuditEvent $event) => $event->occurredAt->gte($since));
    }

    /**
     * Get events from today.
     */
    public function today(): self
    {
        return $this->filter(
            fn (AuditEvent $event) => $event->occurredAt->isToday()
        );
    }

    /**
     * Get order-related events.
     */
    public function orderEvents(): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->type->isOrderEvent());
    }

    /**
     * Get inventory-related events.
     */
    public function inventoryEvents(): self
    {
        return $this->filter(fn (AuditEvent $event) => $event->type->isInventoryEvent());
    }

    /**
     * Group by event type.
     *
     * @return Collection<string, self>
     */
    public function groupByType(): Collection
    {
        return $this->groupBy(fn (AuditEvent $event) => $event->type->value)
            ->map(fn (Collection $events) => new self($events));
    }

    /**
     * Group by user.
     *
     * @return Collection<string, self>
     */
    public function groupByUser(): Collection
    {
        return $this->groupBy(fn (AuditEvent $event) => $event->userName)
            ->map(fn (Collection $events) => new self($events));
    }

    /**
     * Group by date (day).
     *
     * @return Collection<string, self>
     */
    public function groupByDate(): Collection
    {
        return $this->groupBy(fn (AuditEvent $event) => $event->occurredAt->toDateString())
            ->map(fn (Collection $events) => new self($events));
    }

    /**
     * Get event type statistics.
     */
    public function typeStatistics(): array
    {
        $grouped = $this->groupByType();

        return $grouped->map(function (self $events, string $type) {
            return [
                'type' => $type,
                'count' => $events->count(),
                'percentage' => $this->count() > 0 ? round(($events->count() / $this->count()) * 100, 2) : 0,
            ];
        })->sortByDesc('count')->values()->toArray();
    }

    /**
     * Get user activity statistics.
     */
    public function userStatistics(): array
    {
        $grouped = $this->groupByUser();

        return $grouped->map(function (self $events, string $userName) {
            return [
                'user' => $userName,
                'event_count' => $events->count(),
                'critical_count' => $events->critical()->count(),
                'last_activity' => $events->sortByDesc(fn (AuditEvent $e) => $e->occurredAt)->first()?->occurredAt->toISOString(),
            ];
        })->sortByDesc('event_count')->values()->toArray();
    }

    /**
     * Get timeline data for charts.
     */
    public function timeline(string $interval = 'day'): array
    {
        $grouped = match ($interval) {
            'hour' => $this->groupBy(fn (AuditEvent $e) => $e->occurredAt->format('Y-m-d H:00')),
            'day' => $this->groupBy(fn (AuditEvent $e) => $e->occurredAt->toDateString()),
            'week' => $this->groupBy(fn (AuditEvent $e) => $e->occurredAt->startOfWeek()->toDateString()),
            'month' => $this->groupBy(fn (AuditEvent $e) => $e->occurredAt->format('Y-m')),
            default => $this->groupByDate(),
        };

        return $grouped->map(fn (Collection $events) => [
            'count' => $events->count(),
            'critical_count' => $events->filter(fn (AuditEvent $e) => $e->isCritical())->count(),
        ])->toArray();
    }

    /**
     * Get summary statistics.
     */
    public function summary(): array
    {
        return [
            'total_events' => $this->count(),
            'critical_events' => $this->critical()->count(),
            'informational_events' => $this->informational()->count(),
            'unique_users' => $this->unique(fn (AuditEvent $e) => $e->userName)->count(),
            'date_range' => [
                'from' => $this->min(fn (AuditEvent $e) => $e->occurredAt)?->toISOString(),
                'to' => $this->max(fn (AuditEvent $e) => $e->occurredAt)?->toISOString(),
            ],
            'order_events' => $this->orderEvents()->count(),
            'inventory_events' => $this->inventoryEvents()->count(),
        ];
    }
}
