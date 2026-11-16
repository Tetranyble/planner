<?php

namespace App\Domain\Content;

/** A durable operator plan is NEVER a dispatch authorization. */
final class PublicationIntentRules
{
    private const HARD_BLOCKERS = [
        'CONTENT_NOT_DRAFT', 'SUPERSEDED_CONTENT_VERSION', 'APPROVAL_NOT_APPROVED',
        'APPROVAL_EXPIRED', 'ACTIVE_POLICY_CHANGED', 'APPROVED_SNAPSHOT_MISMATCH',
        'INSUFFICIENT_INDEPENDENT_REVIEWS', 'CANONICAL_MEDIA_NOT_VERIFIED',
    ];

    private const HOLD_ONLY_BLOCKERS = [
        'PUBLISHING_ENGINE_NOT_IMPLEMENTED', 'TARGET_AUTOMATIC_PUBLISHING_NOT_ENABLED',
        'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED', 'PROVIDER_CREDENTIAL_NOT_VERIFIED',
        'PROVIDER_CONNECTION_NOT_ACTIVE', 'SOCIAL_ACCOUNT_NOT_ACTIVE',
    ];

    public static function hardBlockers(): array
    {
        return self::HARD_BLOCKERS;
    }

    public static function canHold(array $blockers): bool
    {
        // New readiness blockers fail closed until explicitly classified.
        return array_diff($blockers, self::HOLD_ONLY_BLOCKERS) === []
            && array_intersect(self::HARD_BLOCKERS, $blockers) === [];
    }

    public static function sameIntent(string $originalRunAt, int $originalApprovalId, string $requestedRunAt, int $requestedApprovalId): bool
    {
        return $originalApprovalId === $requestedApprovalId && $originalRunAt === $requestedRunAt;
    }
}
