<?php

namespace App\Domain\Publishing\Delivery;

/** Small sanitized mock reply; no provider raw response or arbitrary headers. */
final readonly class MockHttpReply
{
    public function __construct(
        public ?int $status,
        public ?string $receipt = null,
        public ?int $retryAfter = null,
        public bool $requestMayHaveBeenSent = true,
    ) {
        if ($status !== null && ($status < 100 || $status > 599)) {
            throw new \InvalidArgumentException('Bad mock status');
        }
        if ($receipt !== null && ! preg_match('/^MOCK_[A-F0-9]{16}$/', $receipt)) {
            throw new \InvalidArgumentException('Bad synthetic receipt');
        }
        if ($retryAfter !== null && ($retryAfter < 0 || $retryAfter > 86400)) {
            throw new \InvalidArgumentException('Bad retry delay');
        }
    }
}
