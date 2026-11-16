<?php

// Zero-dependency deterministic contract checks; never loads a network client.
$root = dirname(__DIR__);
foreach ([
    'app/Domain/Content/ProviderPublishCapabilityMatrix.php',
    'app/Domain/Publishing/Delivery/DispatchEnvelope.php',
    'app/Domain/Publishing/Delivery/DispatchOutcome.php',
    'app/Domain/Publishing/Delivery/ProviderDispatchContract.php',
    'app/Domain/Publishing/Delivery/ProviderFailureClassifier.php',
    'app/Domain/Publishing/Delivery/RetrySafetyPolicy.php',
    'app/Domain/Publishing/Delivery/DispatchSafetyGate.php',
    'app/Domain/Publishing/Delivery/MockHttpExchange.php',
    'app/Domain/Publishing/Delivery/MockHttpReply.php',
    'app/Domain/Publishing/Delivery/MockHttpTransportContract.php',
    'app/Domain/Publishing/Delivery/FixtureMockTransport.php',
    'app/Domain/Publishing/Delivery/MockProviderRequestCompiler.php',
    'app/Domain/Publishing/Delivery/MockProviderDispatchAdapter.php',
    'app/Domain/Publishing/Delivery/OfflineMockDeliveryHarness.php',
    'app/Domain/Publishing/Delivery/SimulationAdapter.php',
    'app/Domain/Publishing/Delivery/SimulationHarness.php',
] as $file) {
    require_once $root.'/'.$file;
}
use App\Domain\Publishing\Delivery\DispatchEnvelope;
use App\Domain\Publishing\Delivery\MockHttpExchange;
use App\Domain\Publishing\Delivery\MockProviderRequestCompiler;
use App\Domain\Publishing\Delivery\OfflineMockDeliveryHarness;
use App\Domain\Publishing\Delivery\SimulationHarness;

$assertions = 0;
$check = function ($pass, $message) use (&$assertions) {
    $assertions++;
    if (! $pass) {
        throw new RuntimeException('FAIL: '.$message);
    }
};
foreach (['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'] as $provider) {
    foreach (['confirmed', 'accepted_no_receipt', 'rate_limited', 'rejected', 'server_error', 'timeout', 'failed_before_send'] as $fixture) {
        $r = OfflineMockDeliveryHarness::run($provider, $fixture);
        $check($r['network_requests'] === 0 && $r['live_publications'] === 0 && ! $r['safety_gate']['may_dispatch'], 'offline invariant');
        $check($r['mock_exchanges'] === 1 && str_starts_with($r['mock_request']['path'], '/mock/v1/'), 'synthetic transport');
        if ($fixture === 'confirmed') {
            $check($r['outcome']['kind'] === 'SUCCESS' && $r['outcome']['has_provider_receipt'], 'confirmed mock receipt');
        }
        if (in_array($fixture, ['server_error', 'timeout', 'accepted_no_receipt'], true)) {
            $check($r['outcome']['kind'] === 'UNCERTAIN' && $r['retry_policy']['action'] === 'QUARANTINE', 'ambiguous never retry');
        }
        if ($fixture === 'rate_limited') {
            $check($r['retry_policy']['action'] === 'HOLD_FOR_REVIEW', 'unverified idempotency hold');
        }
        $check(SimulationHarness::run($provider, 'mock_'.$fixture)['network_requests'] === 0, 'durable harness integration');
    }
}
foreach (['instagram' => 'TEXT', 'tiktok' => 'IMAGE', 'youtube' => 'TEXT'] as $provider => $media) {
    try {
        MockProviderRequestCompiler::compile(new DispatchEnvelope($provider, 'mock:operation:0001', $media, str_repeat('a', 64), 'MOCK_TARGET'));
        throw new RuntimeException('Invalid media accepted');
    } catch (DomainException $e) {
        $check(true, 'media rejected');
    }
}
try {
    new MockHttpExchange('facebook', 'POST', 'https://facebook.com', ['Content-Type' => 'application/json'], []);
    throw new RuntimeException('External URL accepted');
} catch (InvalidArgumentException $e) {
    $check(true,'external URL rejected');
}
echo "PASS: $assertions mock transport checks (42 provider fixtures; zero HTTP calls)".PHP_EOL;
