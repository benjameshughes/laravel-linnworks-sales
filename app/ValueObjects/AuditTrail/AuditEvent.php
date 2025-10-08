<?php

declare(strict_types=1);

namespace App\ValueObjects\AuditTrail;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Immutable audit event value object.
 *
 * Uses PHP 8.2+ readonly properties for complete immutability.
 */
readonly class AuditEvent implements Arrayable
{
    public function __construct(
        public AuditEventType $type,
        public Carbon $occurredAt,
        public string $description,
        public string $userName,
        public ?string $resourceId = null,
        public ?int $resourceIntId = null,
        public ?array $metadata = null,
    ) {}

    /**
     * Create from Linnworks inventory audit trail response.
     */
    public static function fromInventoryAudit(array $data): self
    {
        return new self(
            type: AuditEventType::fromString($data['AuditType'] ?? 'Unknown'),
            occurredAt: Carbon::parse($data['AuditTrailDate']),
            description: $data['AuditText'] ?? '',
            userName: $data['UserName'] ?? 'System',
            resourceId: $data['StockItemId'] ?? null,
            resourceIntId: $data['StockItemIntId'] ?? null,
        );
    }

    /**
     * Create from order audit trail response.
     */
    public static function fromOrderAudit(array $data): self
    {
        return new self(
            type: AuditEventType::fromString($data['EventType'] ?? 'Unknown'),
            occurredAt: Carbon::parse($data['EventDate'] ?? $data['DateStamp'] ?? now()),
            description: $data['Note'] ?? $data['EventDetail'] ?? '',
            userName: $data['UserName'] ?? $data['Author'] ?? 'System',
            resourceId: $data['OrderId'] ?? null,
            metadata: $data['Metadata'] ?? null,
        );
    }

    /**
     * Check if event is a critical change.
     */
    public function isCritical(): bool
    {
        return $this->type->isCritical();
    }

    /**
     * Check if event is informational.
     */
    public function isInformational(): bool
    {
        return $this->type->isInformational();
    }

    /**
     * Get event severity level.
     */
    public function severity(): string
    {
        return $this->type->severity();
    }

    /**
     * Get human-readable timestamp.
     */
    public function formattedTimestamp(): string
    {
        return $this->occurredAt->format('Y-m-d H:i:s');
    }

    /**
     * Get relative time (e.g., "2 hours ago").
     */
    public function relativeTime(): string
    {
        return $this->occurredAt->diffForHumans();
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'type_name' => $this->type->name,
            'occurred_at' => $this->occurredAt->toISOString(),
            'occurred_at_formatted' => $this->formattedTimestamp(),
            'relative_time' => $this->relativeTime(),
            'description' => $this->description,
            'user_name' => $this->userName,
            'resource_id' => $this->resourceId,
            'resource_int_id' => $this->resourceIntId,
            'metadata' => $this->metadata,
            'severity' => $this->severity(),
            'is_critical' => $this->isCritical(),
        ];
    }

    /**
     * Convert to string for logging.
     */
    public function toString(): string
    {
        return sprintf(
            '[%s] %s - %s by %s',
            $this->formattedTimestamp(),
            $this->type->name,
            $this->description,
            $this->userName
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
