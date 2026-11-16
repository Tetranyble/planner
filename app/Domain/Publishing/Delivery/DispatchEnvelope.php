<?php

namespace App\Domain\Publishing\Delivery;

/** Immutable, sanitized payload description; never carries OAuth credentials. */
final readonly class DispatchEnvelope
{
    public function __construct(
        public string $provider,
        public string $operationKey,
        public string $mediaKind,
        public string $contentDigest,
        public string $targetRef,
    ) {
        if (! preg_match('/^[a-f0-9]{64}$/', $contentDigest)) {
            throw new \InvalidArgumentException('Invalid content digest');
        }
        if (! preg_match('/^[a-zA-Z0-9:_-]{8,128}$/', $operationKey)) {
            throw new \InvalidArgumentException('Invalid operation key');
        }
        if ($targetRef === '') {
            throw new \InvalidArgumentException('Target is required');
        }
    }
}
