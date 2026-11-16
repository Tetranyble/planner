<?php

namespace App\Domain\Publishing\LiveContracts;

use App\Domain\Publishing\Delivery\DispatchOutcome;
use App\Domain\Publishing\Delivery\ProviderFailureClassifier;

/** Sanitized response decoder for OFFLINE fixture tests; never logs raw provider data. */
final class ProviderReceiptInterpreter
{
    public static function interpret(string $provider, ?int $status, array $responseBody = [], array $responseHeaders = [], bool $mayHaveSent = true): DispatchOutcome
    {
        if ($status === null || $status < 200 || $status >= 300) {
            return ProviderFailureClassifier::classify($status, $mayHaveSent);
        }
        $receipt = match (strtolower($provider)) {
            'facebook' => $responseBody['id'] ?? null,
            'x' => $responseBody['data']['id'] ?? null,
            'linkedin' => self::header($responseHeaders, 'x-restli-id'),
            default => null,
        };
        // Never trust arbitrary payloads as confirmation. An invalid receipt is UNCERTAIN.
        if (! is_string($receipt) || strlen($receipt) > 160 || ! preg_match('/^[a-zA-Z0-9:_-]{3,160}$/D', $receipt)) {
            return new DispatchOutcome('UNCERTAIN', 'NO_VERIFIABLE_RECEIPT', $status);
        }

        return new DispatchOutcome('SUCCESS', 'PROVIDER_RECEIPT', $status, null, $receipt);
    }

    private static function header(array $headers, string $name): mixed
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? ($value[0] ?? null) : $value;
            }
        }

        return null;
    }
}
