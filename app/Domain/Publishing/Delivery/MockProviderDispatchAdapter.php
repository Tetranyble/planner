<?php

namespace App\Domain\Publishing\Delivery;

/** Explicit mock adapter; MUST NOT be registered as a live publishing adapter. */
final readonly class MockProviderDispatchAdapter implements ProviderDispatchContract
{
    public function __construct(private string $network, private MockHttpTransportContract $transport) {}

    public function provider(): string
    {
        return $this->network;
    }

    public function dispatch(DispatchEnvelope $envelope): DispatchOutcome
    {
        if ($envelope->provider !== $this->network) {
            throw new \DomainException('Provider mismatch');
        }
        $request = MockProviderRequestCompiler::compile($envelope);
        $reply = $this->transport->exchange($request);
        if ($reply->status !== null && $reply->status >= 200 && $reply->status < 300 && $reply->receipt !== null) {
            return new DispatchOutcome('SUCCESS', 'MOCK_RECEIPT', $reply->status, null, $reply->receipt);
        }

        return ProviderFailureClassifier::classify($reply->status, $reply->requestMayHaveBeenSent, $reply->retryAfter);
    }
}
