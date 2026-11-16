<?php

namespace App\Domain\Publishing\Delivery;

/** Deterministic in-process fixture. Never sends bytes over a socket. */
final class FixtureMockTransport implements MockHttpTransportContract
{
    public const CASES = ['confirmed', 'accepted_no_receipt', 'rate_limited', 'rejected', 'server_error', 'timeout', 'failed_before_send'];

    private int $exchanges = 0;

    public function __construct(private readonly string $scenario)
    {
        if (! in_array($scenario, self::CASES, true)) {
            throw new \InvalidArgumentException('Unknown mock fixture');
        }
    }

    public function exchange(MockHttpExchange $request): MockHttpReply
    {
        $this->exchanges++;
        $receipt = 'MOCK_'.strtoupper(substr(hash('sha256', $request->provider.'|'.$request->body['digest'].'|'.$request->body['operation_key']), 0, 16));

        return match ($this->scenario) {
            'confirmed' => new MockHttpReply(201, $receipt),
            'accepted_no_receipt' => new MockHttpReply(202),
            'rate_limited' => new MockHttpReply(429, null, 120),
            'rejected' => new MockHttpReply(403),
            'server_error' => new MockHttpReply(503),
            'timeout' => new MockHttpReply(null),
            'failed_before_send' => new MockHttpReply(503, null, null, false),
        };
    }

    public function exchangeCount(): int
    {
        return $this->exchanges;
    }
}
