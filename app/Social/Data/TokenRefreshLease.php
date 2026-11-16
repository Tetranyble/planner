<?php

namespace App\Social\Data;

/** Short-lived in-process secret object, never a queue payload or HTTP resource. */
final readonly class TokenRefreshLease
{
    public function __construct(
        public int $connectionId,
        public string $provider,
        public string $leaseToken,
        public string $refreshToken,
    ) {}
}
