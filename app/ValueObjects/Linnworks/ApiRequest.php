<?php

namespace App\ValueObjects\Linnworks;

use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ApiRequest implements JsonSerializable
{
    public function __construct(
        public string $endpoint,
        public string $method = 'GET',
        public Collection $parameters = new Collection(),
        public Collection $headers = new Collection(),
        public int $timeout = 30,
        public bool $requiresAuth = true,
        public bool $asJson = false,
    ) {}

    public static function get(string $endpoint, array $parameters = []): self
    {
        return new self(
            endpoint: $endpoint,
            method: 'GET',
            parameters: collect($parameters),
        );
    }

    public static function post(string $endpoint, array $parameters = []): self
    {
        return new self(
            endpoint: $endpoint,
            method: 'POST',
            parameters: collect($parameters),
        );
    }

    public static function put(string $endpoint, array $parameters = []): self
    {
        return new self(
            endpoint: $endpoint,
            method: 'PUT',
            parameters: collect($parameters),
        );
    }

    public static function delete(string $endpoint, array $parameters = []): self
    {
        return new self(
            endpoint: $endpoint,
            method: 'DELETE',
            parameters: collect($parameters),
        );
    }

    public function withHeader(string $key, string $value): self
    {
        return new self(
            endpoint: $this->endpoint,
            method: $this->method,
            parameters: $this->parameters,
            headers: $this->headers->put($key, $value),
            timeout: $this->timeout,
            requiresAuth: $this->requiresAuth,
            asJson: $this->asJson,
        );
    }

    public function withTimeout(int $timeout): self
    {
        return new self(
            endpoint: $this->endpoint,
            method: $this->method,
            parameters: $this->parameters,
            headers: $this->headers,
            timeout: $timeout,
            requiresAuth: $this->requiresAuth,
            asJson: $this->asJson,
        );
    }

    public function withoutAuth(): self
    {
        return new self(
            endpoint: $this->endpoint,
            method: $this->method,
            parameters: $this->parameters,
            headers: $this->headers,
            timeout: $this->timeout,
            requiresAuth: false,
            asJson: $this->asJson,
        );
    }

    public function asJson(): self
    {
        return new self(
            endpoint: $this->endpoint,
            method: $this->method,
            parameters: $this->parameters,
            headers: $this->headers->put('Content-Type', 'application/json')->put('Accept', 'application/json'),
            timeout: $this->timeout,
            requiresAuth: $this->requiresAuth,
            asJson: true,
        );
    }

    public function toArray(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'parameters' => $this->parameters->toArray(),
            'headers' => $this->headers->toArray(),
            'timeout' => $this->timeout,
            'requires_auth' => $this->requiresAuth,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getCacheKey(): string
    {
        return md5($this->endpoint . serialize($this->parameters->toArray()));
    }
}