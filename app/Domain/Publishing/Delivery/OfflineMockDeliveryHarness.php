<?php

namespace App\Domain\Publishing\Delivery;

/** In-memory HTTP-shaped contract rehearsal. Never reaches the network. */
final class OfflineMockDeliveryHarness
{
    public static function run(string $provider, string $fixture, string $mediaKind = 'VIDEO'): array
    {
        $transport = new FixtureMockTransport($fixture);
        $envelope = new DispatchEnvelope($provider, 'mock:operation:0001', $mediaKind, str_repeat('c', 64), 'MOCK_TARGET');
        $request = MockProviderRequestCompiler::compile($envelope);
        $result = (new MockProviderDispatchAdapter($provider, $transport))->dispatch($envelope);

        return [
            'provider' => $provider, 'fixture' => $fixture, 'media_kind' => $mediaKind,
            'mock_request' => ['method' => $request->method, 'path' => $request->path, 'body_keys' => array_keys($request->body)],
            'outcome' => $result->redacted(),
            'retry_policy' => RetrySafetyPolicy::decision($result, 1, 3, false),
            'safety_gate' => DispatchSafetyGate::evaluate([]),
            'mock_exchanges' => $transport->exchangeCount(),
            'network_requests' => 0, 'live_publications' => 0,
        ];
    }
}
