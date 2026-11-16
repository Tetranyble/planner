<?php

namespace App\Domain\Publishing\LiveContracts;

use App\Domain\Publishing\Delivery\RetrySafetyPolicy;

/** Deterministic fixture-based provider request/response contract exercise. */
final class ProviderTextContractHarness
{
    public const SCENARIOS = ['confirmed', 'missing_receipt', 'rate_limited', 'permission_denied', 'server_failure', 'timeout'];

    public static function exercise(string $provider, string $accountRef, string $scenario): array
    {
        if (! in_array($scenario, self::SCENARIOS, true)) {
            throw new \InvalidArgumentException('Unknown fixture scenario');
        }
        $request = TextPostRequestCompiler::compile($provider, $accountRef, 'Synthetic contract fixture only');
        [$status,$body,$headers] = match ($scenario) {
            'confirmed' => match ($provider) {
                'facebook' => [200, ['id' => '123_456'], []],
                'linkedin' => [201, [], ['x-restli-id' => 'urn:li:share:123456']],
                'x' => [201, ['data' => ['id' => '123456']], []],
            },
            'missing_receipt' => [202, [], []],
            'rate_limited' => [429, [], []],
            'permission_denied' => [403, [], []],
            'server_failure' => [503, [], []],
            'timeout' => [null, [], []],
        };
        $outcome = ProviderReceiptInterpreter::interpret($provider, $status, $body, $headers);

        return ['request' => $request->metadata(), 'scenario' => $scenario,
            'result' => $outcome->redacted(),
            'retry_decision' => RetrySafetyPolicy::decision($outcome, 1, 3, false),
            'network_requests' => 0, 'credentials_loaded' => false, 'database_writes' => 0];
    }
}
