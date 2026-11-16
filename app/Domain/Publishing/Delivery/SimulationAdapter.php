<?php

namespace App\Domain\Publishing\Delivery;

/** Test-only adapter with deterministic outcomes. Must never be injected as a real provider client. */
final readonly class SimulationAdapter implements ProviderDispatchContract
{
    public function __construct(private string $network, private string $scenario) {}

    public function provider(): string
    {
        return $this->network;
    }

    public function dispatch(DispatchEnvelope $envelope): DispatchOutcome
    {
        if ($envelope->provider !== $this->network) {
            throw new \DomainException('Provider mismatch');
        }

        return match ($this->scenario) {
            'success' => new DispatchOutcome('SUCCESS', 'PROVIDER_RECEIPT', 201, null, 'SIMULATED_RECEIPT_ONLY'),
            'rate_limit' => ProviderFailureClassifier::classify(429, true, 120),
            'rejected' => ProviderFailureClassifier::classify(403, true),
            'server_error' => ProviderFailureClassifier::classify(503, true),
            'safe_before_send' => ProviderFailureClassifier::classify(503, false),
            'timeout' => ProviderFailureClassifier::classify(null, true),
            'missing_receipt' => ProviderFailureClassifier::classify(202, true),
            default => throw new \InvalidArgumentException('Unknown simulation scenario'),
        };
    }
}
