<?php

namespace App\Services\Social;

use App\Authorization\CurrentOrganisation;
use App\Domain\Privacy\MetaReviewDisposition;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Privacy officer must be an active organisation owner, with Kinship capability. */
final class ReviewMetaDataDeletion
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly MetaDeletionReviewRepositoryContract $reviews,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    private function authorize(Organisation $org, User $actor): void
    {
        if (! $org->isActive() || $this->context->idFor($actor) !== (int) $org->id
            || ! $actor->hasPermission('privacy-deletion.review')
            || ! $this->reviews->isActiveOwner($org, $actor)) {
            throw new AuthorizationException('Privacy review requires an authorised organisation owner.');
        }
    }

    public function directory(Organisation $org, User $actor): array
    {
        $this->authorize($org, $actor);

        return $this->reviews->directory($org);
    }

    /** @param array<string,mixed> $input */
    public function resolve(Organisation $org, User $actor, int $targetId, array $input): void
    {
        $decision = MetaReviewDisposition::validate($input);
        $this->authorize($org, $actor);
        DB::transaction(function () use ($org, $actor, $targetId, $decision): void {
            $this->authorize($org, $actor);
            $target = $this->reviews->lockReviewTarget($org, $targetId);
            $this->reviews->resolve($target, $actor, $decision);
            // Never log full evidence, actor identifiers, or other organisation metadata.
            $this->audit->record($org, $actor, 'privacy.meta.deletion.reviewed',
                'meta_data_deletion_target', (string) $target->id, null,
                ['decision' => $decision['disposition'], 'target_id' => $target->id]);
        }, attempts: 3);
    }
}
