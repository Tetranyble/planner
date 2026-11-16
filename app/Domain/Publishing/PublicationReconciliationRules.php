<?php

namespace App\Domain\Publishing;

final class PublicationReconciliationRules
{
    public const KINDS = ['PROVIDER_POST_LOOKUP', 'PROVIDER_SUPPORT_CASE', 'ACCOUNT_ACTIVITY', 'AUDIT_EXPORT', 'OTHER'];

    public const DISPOSITIONS = ['PROVIDER_CONFIRMED', 'NOT_CONFIRMED', 'ESCALATED'];

    public static function canReview(string $state, int $submitterId, int $reviewerId, bool $alreadyReviewed): bool
    {
        return $state === 'UNCERTAIN' && $submitterId !== $reviewerId && ! $alreadyReviewed;
    }
}
