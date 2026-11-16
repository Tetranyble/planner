<?php

namespace App\Domain\Publishing\Delivery;

/** Strictly local synthetic exchange. Not a real provider API request. */
final readonly class MockHttpExchange
{
    public function __construct(
        public string $provider,
        public string $method,
        public string $path,
        public array $headers,
        public array $body,
    ) {
        if ($method !== 'POST' || ! preg_match('~^/mock/v1/[a-z]+/publish$~', $path)) {
            throw new \InvalidArgumentException('Mock transport accepts synthetic POST paths only');
        }
        if ($path !== '/mock/v1/'.$provider.'/publish') {
            throw new \DomainException('Mock provider path mismatch');
        }
        foreach ($headers as $name => $value) {
            if (! in_array(strtolower((string) $name), ['content-type', 'x-mock-operation-key'], true)
                || ! is_string($value)) {
                throw new \InvalidArgumentException('Only synthetic non-secret headers allowed');
            }
        }
        if (($headers['Content-Type'] ?? null) !== 'application/json') {
            throw new \InvalidArgumentException('JSON mock contract required');
        }
        if (isset($body['token']) || isset($body['access_token']) || isset($body['authorization'])) {
            throw new \InvalidArgumentException('Mock payload cannot contain credentials');
        }
    }
}
