<?php

namespace App\Domain\BrandStrategy;

use App\Enums\ClaimCategory;
use App\Enums\ClaimStatus;
use DateTimeImmutable;

/** Pure, conservative predicate. Publish requires an approved, in-window claim and trusted evidence when mandated. */
final class ClaimPublicationEligibility
{
    public function eligible(array $claim, array $policy, bool $verifiedEvidence, DateTimeImmutable $now): bool
    {
        if (($claim['status'] ?? null) !== ClaimStatus::APPROVED->value) {
            return false;
        }
        if (! empty($claim['valid_from']) && new DateTimeImmutable($claim['valid_from']) > $now) {
            return false;
        }
        if (! empty($claim['valid_until']) && new DateTimeImmutable($claim['valid_until']) < $now) {
            return false;
        }
        $category = $claim['claim_category'] ?? null;
        if ($category === ClaimCategory::OUTCOME->value && ! ($policy['allow_guaranteed_outcome_claims'] ?? false)) {
            return false;
        }
        if ($category === ClaimCategory::QUANTIFIED->value && ($policy['evidence_required_for_quantified'] ?? true) && ! $verifiedEvidence) {
            return false;
        }
        if ($category === ClaimCategory::COMPARATIVE->value && ($policy['evidence_required_for_comparisons'] ?? true) && ! $verifiedEvidence) {
            return false;
        }

        return true;
    }
}
