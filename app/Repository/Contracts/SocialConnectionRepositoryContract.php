<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialAccount;
use App\Models\SocialOauthAttempt;
use App\Models\User;
use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;

interface SocialConnectionRepositoryContract
{
    public function createAttempt(Organisation $organisation, Brand $brand, User $actor,
        string $provider, string $digest, string $redirectUri): void;

    public function consumeAttempt(string $digest, string $provider, User $actor): SocialOauthAttempt;

    /** @return array{organisation:Organisation,brand:Brand} */
    public function findAttemptScope(SocialOauthAttempt $attempt): array;

    /** @return array<array<string,mixed>> */
    public function directory(Organisation $organisation, Brand $brand): array;

    /** @param list<DiscoveredSocialAccount> $discovered */
    public function connect(Organisation $organisation, Brand $brand, string $provider,
        OAuthGrant $grant, array $discovered, callable $capabilities): array;

    public function lockScope(Organisation $organisation, Brand $brand): void;

    public function disconnect(Organisation $organisation, Brand $brand, string $accountUuid): SocialAccount;
}
