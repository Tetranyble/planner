<?php

namespace App\Social\Data;

use InvalidArgumentException;

/** Secret-bearing value object; never log, serialize to an API resource or return to a client. */
final readonly class TokenRefreshResult
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
        public ?int $refreshExpiresIn = null,
        public ?array $scopes = null,
        public ?string $actorId = null,
    ) {
        if ($accessToken === '' || $expiresIn <= 0) {
            throw new InvalidArgumentException('Invalid access token refresh response.');
        }
    }
}
