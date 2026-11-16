<?php

namespace App\Domain\Publishing\Delivery;

use App\Domain\Content\ProviderPublishCapabilityMatrix;

/** No DB, no queue, no provider transport, no credential resolution. */
final class SimulationHarness
{
    public const SCENARIOS = ['success', 'rate_limit', 'rejected', 'server_error', 'safe_before_send', 'timeout', 'missing_receipt', 'mock_confirmed', 'mock_accepted_no_receipt', 'mock_rate_limited', 'mock_rejected', 'mock_server_error', 'mock_timeout', 'mock_failed_before_send'];

    public static function run(string $provider, string $scenario, int $attempts = 1, int $maximum = 3, bool $idempotency = false): array
    {
        if (ProviderPublishCapabilityMatrix::capabilityFor($provider) === null) {
            throw new \InvalidArgumentException('Unsupported provider');
        }
        if (str_starts_with($scenario, 'mock_')) {
            $result = OfflineMockDeliveryHarness::run($provider, substr($scenario, 5), 'VIDEO');

            return ['provider' => $provider, 'simulation' => true, 'scenario' => $scenario, 'outcome' => $result['outcome'],
                'policy' => $result['retry_policy'], 'gate' => $result['safety_gate'],
                'mock_exchanges' => $result['mock_exchanges'], 'network_requests' => 0, 'database_writes' => 0];
        }
        $envelope = new DispatchEnvelope($provider, 'simulation:00000001', 'TEXT', str_repeat('a', 64), 'SIMULATED_TARGET');
        $outcome = (new SimulationAdapter($provider, $scenario))->dispatch($envelope);

        return ['provider' => $provider, 'simulation' => true, 'scenario' => $scenario, 'outcome' => $outcome->redacted(),
            'policy' => RetrySafetyPolicy::decision($outcome, $attempts, $maximum, $idempotency),
            'gate' => DispatchSafetyGate::evaluate([]), 'network_requests' => 0, 'database_writes' => 0];
    }
}
