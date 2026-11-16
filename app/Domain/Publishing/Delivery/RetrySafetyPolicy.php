<?php

namespace App\Domain\Publishing\Delivery;

/** Retry only when duplicate side effects are ruled out and request budget remains. */
final class RetrySafetyPolicy
{
    public static function decision(DispatchOutcome $outcome, int $attempts, int $maximum, bool $providerIdempotencyVerified): array
    {
        if ($outcome->kind === 'SUCCESS') {
            return ['action' => 'RECORD_SUCCESS', 'delay_seconds' => null];
        }
        if ($outcome->kind === 'UNCERTAIN') {
            return ['action' => 'QUARANTINE', 'delay_seconds' => null];
        }
        if ($outcome->kind === 'PERMANENT_FAILURE') {
            return ['action' => 'FAIL', 'delay_seconds' => null];
        }
        if ($attempts >= $maximum || $maximum <= 0) {
            return ['action' => 'FAIL', 'delay_seconds' => null];
        }
        if (! $providerIdempotencyVerified) {
            return ['action' => 'HOLD_FOR_REVIEW', 'delay_seconds' => null];
        }
        $backoff = min(3600, 30 * (2 ** min(7, max(0, $attempts - 1))));

        return ['action' => 'RETRY_LATER', 'delay_seconds' => $outcome->kind === 'RATE_LIMITED'
            ? max($backoff, $outcome->retryAfterSeconds ?? 60) : $backoff];
    }
}
