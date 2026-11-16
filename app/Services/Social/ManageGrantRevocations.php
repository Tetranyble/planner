<?php

namespace App\Services\Social;

use App\Authorization\CurrentOrganisation;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\MetaDeletionReviewRepositoryContract;
use App\Repository\Contracts\ProviderGrantRevocationRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Only organisation owners may revoke an OAuth grant shared among multiple brands. */
final class ManageGrantRevocations
{
    public function __construct(private readonly CurrentOrganisation $context,
        private readonly MetaDeletionReviewRepositoryContract $ownerChecker,
        private readonly ProviderGrantRevocationRepositoryContract $repo,
        private readonly AuditEventRepositoryContract $audit) {}

    private function authorize(Organisation $org, User $actor): void
    {
        if (! $org->isActive() || $this->context->idFor($actor) !== (int) $org->id
            || ! $actor->hasPermission('social-grant.revoke')
            || ! $this->ownerChecker->isActiveOwner($org, $actor)) {
            throw new AuthorizationException('An organisation owner with grant revocation permission is required.');
        }
    }

    public function directory(Organisation $org, User $actor): array
    {
        $this->authorize($org, $actor);

        return $this->repo->directory($org);
    }

    public function prepare(Organisation $org, Brand $brand, User $actor, string $accountUuid, string $reason): array
    {
        $this->authorize($org, $actor);
        if ((int) $brand->organisation_id !== (int) $org->id) {
            throw new AuthorizationException;
        }
        if (mb_strlen(trim($reason)) < 12 || mb_strlen($reason) > 250) {
            throw ValidationException::withMessages(['reason' => 'Provide a reason of 12–250 characters.']);
        }

        return DB::transaction(function () use ($org, $brand, $actor, $accountUuid, $reason): array {
            $this->authorize($org, $actor);
            $request = $this->repo->prepare($org, $brand, $accountUuid, (int) $actor->id, trim($reason));
            $this->audit->record($org, $actor, 'social.grant.revocation.prepared',
                'provider_grant_revocation_request', $request->uuid, $brand,
                ['provider' => $request->provider, 'impact_count' => (int) $request->affected_connections]);

            return ['uuid' => $request->uuid, 'status' => $request->status,
                'provider' => $request->provider, 'affected_connections' => (int) $request->affected_connections,
                'expires_at' => $request->expires_at->toIso8601String(),
                'remote_execution_supported' => $request->provider === 'tiktok'];
        }, attempts: 3);
    }

    public function confirm(Organisation $org, User $actor, string $requestUuid): array
    {
        $this->authorize($org, $actor);

        return DB::transaction(function () use ($org, $actor, $requestUuid): array {
            $this->authorize($org, $actor);
            $request = $this->repo->confirm($org, $requestUuid, (int) $actor->id);
            $this->audit->record($org, $actor, 'social.grant.revocation.confirmed',
                'provider_grant_revocation_request', $request->uuid, null,
                ['provider' => $request->provider, 'state' => $request->status]);

            return ['uuid' => $request->uuid, 'status' => $request->status];
        }, attempts: 3);
    }
}
