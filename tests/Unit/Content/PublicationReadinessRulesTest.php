<?php

namespace Tests\Unit\Content;

use App\Domain\Content\PublicationReadinessRules;
use PHPUnit\Framework\TestCase;

final class PublicationReadinessRulesTest extends TestCase
{
    private function evaluate(array $changes = []): array
    {
        $values = array_replace([
            'itemState' => 'DRAFT', 'targetVersion' => 2, 'latestVersion' => 2,
            'approvalStatus' => 'APPROVED', 'approvalExpired' => false,
            'policyMatches' => true, 'snapshotMatches' => true,
            'independentApprovals' => 2, 'requiredApprovals' => 2,
            'accountActive' => true, 'connectionActive' => true,
            'credentialValid' => true, 'mediaVerified' => true,
            'automaticCapabilityVerified' => true,
        ], $changes);

        return PublicationReadinessRules::blockers(...array_values($values));
    }

    public function test_all_evidence_checks_can_pass_without_issuing_publish_permission(): void
    {
        $this->assertSame([], $this->evaluate());
    }

    public function test_a_changed_snapshot_and_policy_fail_closed(): void
    {
        $this->assertSame(['ACTIVE_POLICY_CHANGED', 'APPROVED_SNAPSHOT_MISMATCH'],
            $this->evaluate(['policyMatches' => false, 'snapshotMatches' => false]));
    }

    public function test_version_credential_media_and_capability_failures_are_independent(): void
    {
        $failures = $this->evaluate([
            'latestVersion' => 3, 'credentialValid' => false,
            'mediaVerified' => false, 'automaticCapabilityVerified' => false,
        ]);
        $this->assertSame([
            'SUPERSEDED_CONTENT_VERSION', 'PROVIDER_CREDENTIAL_NOT_VERIFIED',
            'CANONICAL_MEDIA_NOT_VERIFIED', 'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED',
        ], $failures);
    }

    public function test_pending_expired_and_under_quorum_reviews_cannot_pass(): void
    {
        $this->assertSame([
            'APPROVAL_NOT_APPROVED', 'APPROVAL_EXPIRED', 'INSUFFICIENT_INDEPENDENT_REVIEWS',
        ], $this->evaluate(['approvalStatus' => 'PENDING', 'approvalExpired' => true, 'independentApprovals' => 1]));
    }
}
