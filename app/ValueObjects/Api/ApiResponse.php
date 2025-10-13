<?php

namespace App\ValueObjects\Api;

use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ApiResponse implements JsonSerializable
{
    public function __construct(
        public Collection $data,
        public Collection $meta,
        public ?Collection $errors = null,
        public int $statusCode = 200,
    ) {}

    public static function success(Collection $data, ?Collection $meta = null): self
    {
        return new self(
            data: $data,
            meta: $meta ?? collect(),
            statusCode: 200,
        );
    }

    public static function error(string $message, int $statusCode = 400, ?Collection $meta = null): self
    {
        return new self(
            data: collect(),
            meta: $meta ?? collect(),
            errors: collect(['message' => $message]),
            statusCode: $statusCode,
        );
    }

    public static function notFound(string $message = 'Resource not found', ?Collection $meta = null): self
    {
        return self::error($message, 404, $meta);
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isError(): bool
    {
        return ! $this->isSuccess();
    }

    public function jsonSerialize(): array
    {
        $response = [];

        if ($this->data->isNotEmpty()) {
            $response['data'] = $this->data;
        }

        if ($this->meta->isNotEmpty()) {
            $response['meta'] = $this->meta;
        }

        if ($this->errors && $this->errors->isNotEmpty()) {
            $response['errors'] = $this->errors;
        }

        return $response;
    }

    public function toJsonResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->jsonSerialize(), $this->statusCode);
    }
}
