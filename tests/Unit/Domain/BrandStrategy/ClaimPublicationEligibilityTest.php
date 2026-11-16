<?php

namespace Tests\Unit\Domain\BrandStrategy;

use App\Domain\BrandStrategy\ClaimPublicationEligibility;
use PHPUnit\Framework\TestCase;

final class ClaimPublicationEligibilityTest extends TestCase
{
    public function test_draft_or_expired_claim_is_never_eligible(): void
    {
        $policy = ['approved_claims_only' => false];
        $subject = new ClaimPublicationEligibility;
        $now = new \DateTimeImmutable('2026-11-14T10:00:00+01:00');
        self::assertFalse($subject->eligible(['status' => 'DRAFT', 'claim_category' => 'OTHER'], $policy, true, $now));
        self::assertFalse($subject->eligible(['status' => 'APPROVED', 'claim_category' => 'OTHER',
            'valid_until' => '2026-11-13T10:00:00+01:00'], $policy, true, $now));
        self::assertFalse($subject->eligible(['status' => 'APPROVED', 'claim_category' => 'OTHER',
            'valid_from' => '2026-11-15T10:00:00+01:00'], $policy, true, $now));
    }

    public function test_quantified_and_comparative_claims_require_verified_evidence_by_default(): void
    {
        $subject = new ClaimPublicationEligibility;
        $now = new \DateTimeImmutable('2026-11-14');
        foreach (['QUANTIFIED', 'COMPARATIVE'] as $category) {
            self::assertFalse($subject->eligible(['status' => 'APPROVED', 'claim_category' => $category], [], false, $now));
            self::assertTrue($subject->eligible(['status' => 'APPROVED', 'claim_category' => $category], [], true, $now));
        }
    }

    public function test_outcome_claims_are_denied_by_default_and_explicit_override_is_required(): void
    {
        $subject = new ClaimPublicationEligibility;
        $claim = ['status' => 'APPROVED', 'claim_category' => 'OUTCOME'];
        $now = new \DateTimeImmutable('2026-11-14');
        self::assertFalse($subject->eligible($claim, [], true, $now));
        self::assertTrue($subject->eligible($claim, ['allow_guaranteed_outcome_claims' => true], true, $now));
    }
}
