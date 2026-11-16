<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialOAuthSelection;
use App\Models\User;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;

interface SocialOAuthSelectionRepositoryContract
{
    /** @param list<DiscoveredSocialAccount> $accounts */
    public function create(Organisation $organisation, Brand $brand, User $actor, string $provider, OAuthGrant $grant, array $accounts): SocialOAuthSelection;

    public function findForActor(Organisation $organisation, Brand $brand, User $actor, string $selectionUuid): SocialOAuthSelection;

    public function lockForConsumption(Organisation $organisation, Brand $brand, User $actor, string $selectionUuid): SocialOAuthSelection;

    /** @return list<DiscoveredSocialAccount> */
    public function accounts(SocialOAuthSelection $selection): array;

    public function grant(SocialOAuthSelection $selection): OAuthGrant;

    public function markConsumed(SocialOAuthSelection $selection): void;

    public function pruneExpired(): int;
}
