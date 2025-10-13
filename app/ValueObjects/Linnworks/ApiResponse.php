<?php

namespace App\ValueObjects\Linnworks;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ApiResponse implements JsonSerializable
{
    public function __construct(
        public Collection $data,
        public int $statusCode,
        public Collection $headers = new Collection,
        public ?string $error = null,
        public ?Collection $meta = null,
        public Carbon $requestedAt = new Carbon,
    ) {}

    public static function success(array|Collection $data, int $statusCode = 200): self
    {
        return new self(
            data: $data instanceof Collection ? $data : collect($data),
            statusCode: $statusCode,
            requestedAt: now(),
        );
    }

    public static function error(string $error, int $statusCode = 400): self
    {
        return new self(
            data: collect(),
            statusCode: $statusCode,
            error: $error,
            requestedAt: now(),
        );
    }

    public static function fromHttpResponse(\Illuminate\Http\Client\Response $response): self
    {
        // Handle plain text responses (like inventory count)
        $contentType = $response->header('Content-Type') ?? '';

        if (str_contains($contentType, 'application/json')) {
            $data = collect($response->json());
        } else {
            // Plain text response - store the raw body as the first item
            $data = collect([$response->body()]);
        }

        return new self(
            data: $data,
            statusCode: $response->status(),
            headers: collect($response->headers()),
            error: $response->failed() ? $response->reason() : null,
            requestedAt: now(),
        );
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300 && $this->error === null;
    }

    public function isError(): bool
    {
        return ! $this->isSuccess();
    }

    public function hasData(): bool
    {
        return $this->data->isNotEmpty();
    }

    public function getData(): Collection
    {
        return $this->data;
    }

    public function getFirstItem(): mixed
    {
        return $this->data->first();
    }

    public function toArray(): array
    {
        return $this->data->toArray();
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
            'status_code' => $this->statusCode,
            'headers' => $this->headers,
            'error' => $this->error,
            'meta' => $this->meta,
            'requested_at' => $this->requestedAt->toISOString(),
        ];
    }

    public function withMeta(array $meta): self
    {
        return new self(
            data: $this->data,
            statusCode: $this->statusCode,
            headers: $this->headers,
            error: $this->error,
            meta: collect($meta),
            requestedAt: $this->requestedAt,
        );
    }

    public function getRateLimit(): ?int
    {
        return $this->headers->get('X-RateLimit-Remaining') !== null
            ? (int) $this->headers->get('X-RateLimit-Remaining')
            : null;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /**
     * Get raw response data (for plain text responses)
     */
    public function getRawResponse(): string
    {
        // For plain text responses, the data collection should contain the raw response
        return $this->data->first() ?? '';
    }
}
