<?php

namespace App\ValueObjects\Linnworks;

use Carbon\Carbon;
use JsonSerializable;

readonly class SessionToken implements JsonSerializable
{
    private const DEFAULT_EXPIRY_MINUTES = 55;

    public function __construct(
        public string $token,
        public string $server,
        public Carbon $expiresAt,
        public ?string $userId = null,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        $token = $response['Token'] ?? $response['token'] ?? null;
        $server = $response['Server'] ?? $response['server'] ?? null;

        if (!$token || !$server) {
            throw new \InvalidArgumentException('Missing token or server in Linnworks session response.');
        }

        $expiresAt = isset($response['ExpiresAt'])
            ? Carbon::parse($response['ExpiresAt'])
            : now()->addMinutes(config('linnworks.session_ttl', self::DEFAULT_EXPIRY_MINUTES));

        return new self(
            token: $token,
            server: $server,
            expiresAt: $expiresAt,
            userId: $response['UserId'] ?? $response['userId'] ?? null,
        );
    }

    public static function fromCacheData(array $data): self
    {
        return new self(
            token: $data['token'],
            server: $data['server'],
            expiresAt: Carbon::parse($data['expires_at']),
            userId: $data['user_id'] ?? null,
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    public function isExpiringSoon(int $bufferMinutes = 5): bool
    {
        return $this->expiresAt->copy()->subMinutes($bufferMinutes)->isPast();
    }

    public function toCacheData(): array
    {
        return [
            'token' => $this->token,
            'server' => $this->server,
            'expires_at' => $this->expiresAt->toISOString(),
            'user_id' => $this->userId,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toCacheData();
    }

    public function getBaseUrl(): string
    {
        $server = str_starts_with($this->server, 'http')
            ? rtrim($this->server, '/')
            : 'https://' . ltrim($this->server, '/');

        return $server . '/api/';
    }

    public function getAuthHeaders(): array
    {
        return [
            'Authorization' => $this->token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
