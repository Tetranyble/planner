<?php

namespace App\Repository\Support;

use InvalidArgumentException;

/**
 * Explicit, validated scope for brand-owned database records.
 * This is not an authorization decision: services must authorize the actor
 * before passing scope identifiers to a repository.
 */
final readonly class BrandScope
{
    public function __construct(
        public int $organisationId,
        public int $brandId,
    ) {
        if ($organisationId <= 0 || $brandId <= 0) {
            throw new InvalidArgumentException('Both organisation and brand identifiers must be positive.');
        }
    }

    /** @param array<string, mixed> $attributes */
    public function assertWritableAttributes(array $attributes): void
    {
        foreach (['id', 'uuid', 'organisation_id', 'brand_id'] as $protected) {
            if (array_key_exists($protected, $attributes)) {
                throw new InvalidArgumentException("The '{$protected}' field cannot be written through a brand-scoped repository.");
            }
        }
    }
}
