<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class ImportOrdersResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly int $processed,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        public readonly int $failed,
    ) {}

    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function summary(): string
    {
        return sprintf(
            '%d processed / %d created / %d updated / %d skipped / %d failed',
            $this->processed,
            $this->created,
            $this->updated,
            $this->skipped,
            $this->failed,
        );
    }
}
