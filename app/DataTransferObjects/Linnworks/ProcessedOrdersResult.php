<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Linnworks;

use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

final class ProcessedOrdersResult implements \ArrayAccess, Arrayable, Countable, IteratorAggregate, JsonSerializable
{
    public function __construct(
        public readonly Collection $orders,
        public readonly bool $hasMorePages,
        public readonly int $totalResults,
        public readonly int $currentPage,
        public readonly int $entriesPerPage,
    ) {}

    public function count(): int
    {
        return $this->orders->count();
    }

    public function getIterator(): Traversable
    {
        return $this->orders->getIterator();
    }

    public function toArray(): array
    {
        return $this->orders->toArray();
    }

    public function jsonSerialize(): array
    {
        return [
            'orders' => $this->toArray(),
            'has_more_pages' => $this->hasMorePages,
            'total_results' => $this->totalResults,
            'current_page' => $this->currentPage,
            'entries_per_page' => $this->entriesPerPage,
        ];
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'orders' => $this->orders,
            'hasMorePages' => $this->hasMorePages,
            'totalResults' => $this->totalResults,
            'currentPage' => $this->currentPage,
            'entriesPerPage' => $this->entriesPerPage,
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, [
            'orders',
            'hasMorePages',
            'totalResults',
            'currentPage',
            'entriesPerPage',
        ], true);
    }

    // ArrayAccess implementation for backwards compatibility
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('ProcessedOrdersResult is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('ProcessedOrdersResult is immutable.');
    }
}
