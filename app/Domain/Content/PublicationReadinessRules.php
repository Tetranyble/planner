<?php

namespace App\Domain\Content;

/** Pure, fail-closed publication-readiness predicates; never grants a publication permit. */
final class PublicationReadinessRules
{
    /** @return list<string> */
    public static function blockers(
        string $itemState,
        int $targetVersion,
        int $latestVersion,
        string $approvalStatus,
        bool $approvalExpired,
        bool $policyMatches,
        bool $snapshotMatches,
        int $independentApprovals,
        int $requiredApprovals,
        bool $accountActive,
        bool $connectionActive,
        bool $credentialValid,
        bool $mediaVerified,
        bool $automaticCapabilityVerified,
    ): array {
        $failures = [];
        if ($itemState !== 'DRAFT') {
            $failures[] = 'CONTENT_NOT_DRAFT';
        }
        if ($targetVersion !== $latestVersion) {
            $failures[] = 'SUPERSEDED_CONTENT_VERSION';
        }
        if ($approvalStatus !== 'APPROVED') {
            $failures[] = 'APPROVAL_NOT_APPROVED';
        }
        if ($approvalExpired) {
            $failures[] = 'APPROVAL_EXPIRED';
        }
        if (! $policyMatches) {
            $failures[] = 'ACTIVE_POLICY_CHANGED';
        }
        if (! $snapshotMatches) {
            $failures[] = 'APPROVED_SNAPSHOT_MISMATCH';
        }
        if ($requiredApprovals < 1 || $independentApprovals < $requiredApprovals) {
            $failures[] = 'INSUFFICIENT_INDEPENDENT_REVIEWS';
        }
        if (! $accountActive) {
            $failures[] = 'SOCIAL_ACCOUNT_NOT_ACTIVE';
        }
        if (! $connectionActive) {
            $failures[] = 'PROVIDER_CONNECTION_NOT_ACTIVE';
        }
        if (! $credentialValid) {
            $failures[] = 'PROVIDER_CREDENTIAL_NOT_VERIFIED';
        }
        if (! $mediaVerified) {
            $failures[] = 'CANONICAL_MEDIA_NOT_VERIFIED';
        }
        if (! $automaticCapabilityVerified) {
            $failures[] = 'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED';
        }

        return $failures;
    }
}
