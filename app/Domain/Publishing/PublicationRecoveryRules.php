<?php

namespace App\Domain\Publishing;

/** Pure, deterministic and deliberately conservative recovery rules. */
final class PublicationRecoveryRules
{
    public static function recoveryState(string $state, bool $expired): ?string
    {
        if (! $expired) {
            return null;
        }

        // A request may already have reached the provider. Never replay it blindly.
        return match ($state) {
            'PUBLISHING' => 'UNCERTAIN',
            'LEASED' => 'AWAITING_USER',
            default => null,
        };
    }

    public static function canAcquire(string $state, bool $engineEnabled, int $attempts, int $maximum): bool
    {
        return $engineEnabled && in_array($state, ['SCHEDULED', 'RETRY_WAIT'], true)
            && $maximum > 0 && $attempts < $maximum;
    }
}
