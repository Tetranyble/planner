<?php

namespace App\Social\Data;

final readonly class DiscoveredSocialAccount
{
    public function __construct(
        public string $externalId,
        public string $kind,
        public string $displayName,
        public ?string $handle = null,
    ) {}
}
