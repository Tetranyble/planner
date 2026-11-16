<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\PublicationRecoveryRules;
use PHPUnit\Framework\TestCase;

final class PublicationRecoveryRulesTest extends TestCase
{
    public function test_expired_publishing_is_uncertain_and_never_automatically_retried(): void
    {
        self::assertSame('UNCERTAIN', PublicationRecoveryRules::recoveryState('PUBLISHING', true));
        self::assertNull(PublicationRecoveryRules::recoveryState('PUBLISHING', false));
        self::assertFalse(PublicationRecoveryRules::canAcquire('UNCERTAIN', true, 0, 5));
    }

    public function test_expired_lease_is_reheld_without_claiming_delivery(): void
    {
        self::assertSame('AWAITING_USER', PublicationRecoveryRules::recoveryState('LEASED', true));
        foreach (['AWAITING_USER', 'CANCELLED', 'PUBLISHED', 'FAILED', 'RETRY_WAIT'] as $state) {
            self::assertNull(PublicationRecoveryRules::recoveryState($state, true));
        }
    }

    public function test_acquisition_requires_explicit_engine_enablement_and_budget(): void
    {
        self::assertFalse(PublicationRecoveryRules::canAcquire('SCHEDULED', false, 0, 5));
        self::assertFalse(PublicationRecoveryRules::canAcquire('RETRY_WAIT', true, 5, 5));
        self::assertFalse(PublicationRecoveryRules::canAcquire('SCHEDULED', true, 0, 0));
        self::assertTrue(PublicationRecoveryRules::canAcquire('SCHEDULED',true,0,5));
    }
}
