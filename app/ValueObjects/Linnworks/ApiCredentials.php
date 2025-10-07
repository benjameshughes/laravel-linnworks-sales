<?php

namespace App\ValueObjects\Linnworks;

use JsonSerializable;

readonly class ApiCredentials implements JsonSerializable
{
    public function __construct(
        public string $applicationId,
        public string $applicationSecret,
        public string $redirectUri,
        public ?string $serverId = null,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            applicationId: config('linnworks.application_id') ?? '',
            applicationSecret: config('linnworks.application_secret') ?? '',
            redirectUri: config('linnworks.redirect_uri') ?? 'https://localhost/linnworks/callback',
            serverId: config('linnworks.server_id'),
        );
    }

    public function toArray(): array
    {
        return [
            'ApplicationId' => $this->applicationId,
            'ApplicationSecret' => $this->applicationSecret,
            'RedirectUri' => $this->redirectUri,
            'ServerId' => $this->serverId,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function isValid(): bool
    {
        return !empty($this->applicationId) 
            && !empty($this->applicationSecret) 
            && !empty($this->redirectUri);
    }
}