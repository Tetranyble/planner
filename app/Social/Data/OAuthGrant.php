<?php

namespace App\Social\Data;

/** Redact this DTO from logs; it contains credentials. */
final readonly class OAuthGrant
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public string $actorId,
        public array $scopes,
        public int $expiresIn,
        public ?int $refreshExpiresIn = null,
    ) {}
}
