<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\PlannerOAuthLink;
use App\Models\PlannerOAuthUser;
use App\Models\User;

interface PlannerOAuthLinkRepositoryContract
{
    /** @return array<int, PlannerAgentDelegation> */
    public function availableDelegations(User $actor): array;

    public function delegationForConsent(User $actor, int $delegationId): ?PlannerAgentDelegation;

    /** One user/client must never silently switch brands or delegated authority. */
    public function bind(User $actor, string $clientId, PlannerAgentDelegation $delegation): PlannerOAuthLink;

    /** @return array{organisation:Organisation,brand:Brand,delegation:PlannerAgentDelegation}|null */
    public function scopeForPassportToken(PlannerOAuthUser $actor, string $clientId): ?array;

    /** @return array<int, array<string, mixed>> */
    public function listForUser(User $actor): array;

    public function revoke(User $actor, int $linkId): bool;
}
