<?php

namespace App\Services\Ai;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Ai\EditorialClaimScanner;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AiClaimReviewRepositoryContract;
use App\Repository\Contracts\AiSuggestionRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ReviewAiSuggestionClaims
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly AiSuggestionRepositoryContract $suggestions,
        private readonly AiClaimReviewRepositoryContract $claims) {}

    public function inspect(User $actor, Organisation $org, Brand $brand, string $uuid): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! $this->guard->allows($actor, $brand, 'content_item.index')) {
            throw new AuthorizationException('Brand editorial review denied.');
        }
        $suggestion = $this->suggestions->find($org, $brand, $uuid);
        if (! hash_equals((string) $suggestion->result_hash, hash('sha256', (string) $suggestion->suggestion_text))) {
            throw new ConflictHttpException('Saved AI suggestion content hash no longer matches.');
        }

        return ['suggestion_uuid' => $suggestion->uuid,
            ...EditorialClaimScanner::scan((string) $suggestion->suggestion_text,
                $this->claims->activeApprovedWording($org, $brand))];
    }
}
