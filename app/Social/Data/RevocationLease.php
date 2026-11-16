<?php

namespace App\Social\Data;

/** Short-lived sensitive DTO. Never serialize, log, or return to HTTP clients. */
final readonly class RevocationLease
{
    public function __construct(public int $requestId, public int $groupId,
        public string $leaseToken, public string $provider,
        public string $accessToken) {}
}
