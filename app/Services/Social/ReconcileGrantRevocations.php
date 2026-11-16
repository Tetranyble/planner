<?php

namespace App\Services\Social;

use App\Authorization\CurrentOrganisation;
use App\Domain\Social\GrantReconciliationRules;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use App\Repository\Contracts\ProviderGrantReconciliationRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Human review is an attestation, not a substituted provider API acknowledgement. */
final class ReconcileGrantRevocations
{
    public function __construct(
        private readonly CurrentOrganisation $context,
        private readonly MetaDeletionReviewRepositoryContract $owners,
        private readonly ProviderGrantReconciliationRepositoryContract $repository,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    private function authorize(Organisation $organisation, User $actor): void
    {
        if (! $organisation->isActive()
            || $this->context->idFor($actor) !== (int) $organisation->id
            || ! $actor->hasPermission('social-grant.reconcile')
            || ! $this->owners->isActiveOwner($organisation, $actor)) {
            throw new AuthorizationException('Organisation owner and grant reconciliation permission required.');
        }
    }

    public function directory(Organisation $organisation, User $actor): array
    {
        $this->authorize($organisation, $actor);

        return $this->repository->directory($organisation, (int) $actor->id);
    }

    public function submit(Organisation $organisation, User $actor, string $uuid, array $data): array
    {
        $this->authorize($organisation, $actor);
        $data = Validator::make($data, GrantReconciliationRules::evidence())->validate();

        return DB::transaction(function () use ($organisation, $actor, $uuid, $data): array {
            $this->authorize($organisation, $actor);
            $evidence = $this->repository->submit($organisation, $uuid, (int) $actor->id, $data);
            $this->audit->record($organisation, $actor, 'social.grant.reconciliation.evidence_added',
                'provider_grant_revocation_request', $uuid, null, ['evidence_uuid' => $evidence->uuid]);

            return ['uuid' => $evidence->uuid, 'submitted_at' => $evidence->created_at?->toIso8601String()];
        }, attempts: 3);
    }

    public function decide(Organisation $organisation, User $actor, string $uuid, array $data): array
    {
        $this->authorize($organisation, $actor);
        $data = Validator::make($data, GrantReconciliationRules::decision())->validate();

        return DB::transaction(function () use ($organisation, $actor, $uuid, $data): array {
            $this->authorize($organisation, $actor);
            $decision = $this->repository->decide($organisation, $uuid, (int) $actor->id, $data);
            $this->audit->record($organisation, $actor, 'social.grant.reconciliation.reviewed',
                'provider_grant_revocation_request', $uuid, null,
                ['decision_uuid' => $decision->uuid, 'outcome' => $decision->outcome]);

            return ['uuid' => $decision->uuid, 'outcome' => $decision->outcome,
                'note' => 'A manual attestation does not constitute a provider API acknowledgement.'];
        }, attempts: 3);
    }
}
