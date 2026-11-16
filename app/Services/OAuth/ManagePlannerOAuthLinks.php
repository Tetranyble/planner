<?php

namespace App\Services\OAuth;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Agent\AgentDelegationPolicy;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use App\Repository\Contracts\PlannerOAuthLinkRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;

/** Consent and revocation are always user-initiated; no agent can grant itself OAuth access. */
final class ManagePlannerOAuthLinks
{
    public function __construct(
        private readonly PlannerOAuthLinkRepositoryContract $links,
        private readonly BrandMemberRepositoryContract $members,
        private readonly CurrentOrganisation $context,
        private readonly BrandPermissionGuard $permissions,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    public function availableDelegations(User $actor): array
    {
        if (! config('planner.oauth_enabled', false)) {
            return [];
        }

        $options = [];
        foreach ($this->links->availableDelegations($actor) as $delegation) {
            $organisation = Organisation::query()->find($delegation->organisation_id);
            $brand = Brand::query()->whereKey($delegation->brand_id)
                ->where('organisation_id', $delegation->organisation_id)->first();
            if (! $organisation || ! $brand) {
                continue;
            }
            try {
                $this->context->select($actor, (int) $organisation->id);
            } catch (\Throwable) {
                // An old delegation cannot reveal a brand after organisation access is revoked.
                continue;
            }
            if (! $this->permissions->allows($actor, $brand, 'approval_request.index')) {
                continue;
            }
            if ($delegation->mode === AgentDelegationPolicy::DELEGATED_PUBLISH) {
                $role = $this->members->organisationMember($organisation, (int) $actor->id)?->role_name;
                if ($role !== 'owner') {
                    continue;
                }
            }
            $options[] = ['id' => (int) $delegation->id, 'brand' => (string) $brand->name,
                'mode' => $delegation->mode, 'name' => $delegation->name,
                'expires_at' => $delegation->expires_at?->toIso8601String()];
        }

        return $options;
    }

    public function approve(User $actor, string $clientId, int $delegationId): void
    {
        if (! config('planner.oauth_enabled', false) || ! config('planner.agent_access_enabled', false)) {
            throw new AuthorizationException('OAuth agent access is disabled.');
        }
        $delegation = $this->links->delegationForConsent($actor, $delegationId);
        if ($delegation === null) {
            throw new AuthorizationException('That delegation is expired, revoked or belongs to another user.');
        }
        $organisation = Organisation::query()->findOrFail($delegation->organisation_id);
        $brand = Brand::query()->whereKey($delegation->brand_id)
            ->where('organisation_id', $organisation->id)->firstOrFail();
        $this->context->select($actor, (int) $organisation->id);
        if (! $this->permissions->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Brand access has changed.');
        }
        if ($delegation->mode === AgentDelegationPolicy::DELEGATED_PUBLISH) {
            $role = $this->members->organisationMember($organisation, (int) $actor->id)?->role_name;
            if ($role !== 'owner') {
                throw new AuthorizationException('Publishing delegation requires a current organisation owner.');
            }
        }
        $link = $this->links->bind($actor, $clientId, $delegation);
        $this->audit->record($organisation, $actor, 'oauth.agent.consent',
            'planner_oauth_link', (string) $link->id, $brand,
            ['client_id_digest' => hash('sha256', $clientId), 'delegation_id' => $delegation->id]);
    }

    public function listing(User $actor): array
    {
        return $this->links->listForUser($actor);
    }

    public function revoke(User $actor, int $id): bool
    {
        // Atomic token/refresh-token revocation happens at the repository boundary.
        return $this->links->revoke($actor, $id);
    }
}
