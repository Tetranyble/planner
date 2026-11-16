<?php

namespace App\Social\Data;

/** Ephemeral internal-only data. Never serialize, cache, log or send this to a client. */
final readonly class MetaInspectionLease
{
    public function __construct(
        public int $connectionId,
        public string $leaseToken,
        public string $accessToken,
        public string $tokenDigest,
        public string $expectedActorId,
    ) {}
}
