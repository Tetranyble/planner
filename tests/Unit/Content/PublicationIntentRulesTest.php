<?php

namespace Tests\Unit\Content;

use App\Domain\Content\PublicationIntentRules;
use PHPUnit\Framework\TestCase;

final class PublicationIntentRulesTest extends TestCase
{
    public function test_only_non_content_eligibility_blockers_can_be_held(): void
    {
        self::assertTrue(PublicationIntentRules::canHold([
            'PUBLISHING_ENGINE_NOT_IMPLEMENTED', 'TARGET_AUTOMATIC_PUBLISHING_NOT_ENABLED',
            'PROVIDER_CREDENTIAL_NOT_VERIFIED', 'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED',
        ]));
        self::assertFalse(PublicationIntentRules::canHold(['UNKNOWN_NEW_RISK']));
        foreach (['CONTENT_NOT_DRAFT', 'SUPERSEDED_CONTENT_VERSION', 'APPROVAL_NOT_APPROVED', 'APPROVAL_EXPIRED',
            'ACTIVE_POLICY_CHANGED', 'APPROVED_SNAPSHOT_MISMATCH', 'INSUFFICIENT_INDEPENDENT_REVIEWS',
            'CANONICAL_MEDIA_NOT_VERIFIED'] as $reason) {
            self::assertFalse(PublicationIntentRules::canHold([$reason]), $reason);
        }
    }

    public function test_idempotency_key_reuse_requires_same_approval_and_utc_time(): void
    {
        self::assertTrue(PublicationIntentRules::sameIntent('2026-12-01 10:00:00', 42, '2026-12-01 10:00:00', 42));
        self::assertFalse(PublicationIntentRules::sameIntent('2026-12-01 10:00:00', 42, '2026-12-01 11:00:00', 42));
        self::assertFalse(PublicationIntentRules::sameIntent('2026-12-01 10:00:00', 42, '2026-12-01 10:00:00', 43));
    }
}
