<?php

namespace App\Services\Social;

use App\Authorization\CurrentOrganisation;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use App\Repository\Contracts\PrivacyRetentionFollowupRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ManagePrivacyRetentionFollowups
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly MetaDeletionReviewRepositoryContract $reviews,
        private readonly PrivacyRetentionFollowupRepositoryContract $repository,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    private function authorize(Organisation $org, User $actor): void
    {
        if (! $org->isActive() || $this->context->idFor($actor) !== (int) $org->id
            || ! $actor->hasPermission('privacy-deletion.review')
            || ! $this->reviews->isActiveOwner($org, $actor)) {
            throw new AuthorizationException('Privacy retention review requires an organisation owner.');
        }
    }

    public function directory(Organisation $org, User $actor): array
    {
        $this->authorize($org, $actor);

        return $this->repository->directory($org);
    }

    public function acknowledge(Organisation $org, User $actor, int $id, string $notes): void
    {
        $this->authorize($org, $actor);
        DB::transaction(function () use ($org, $actor, $id, $notes): void {
            $this->authorize($org, $actor);
            $this->repository->acknowledge($org, $actor, $id, $notes);
            $this->audit->record($org, $actor, 'privacy.retention.followup.acknowledged',
                'privacy_retention_followup', (string) $id);
        }, attempts: 3);
    }

    public function enqueueDue(int $limit): int
    {
        return $this->repository->enqueueDue($limit);
    }
}
