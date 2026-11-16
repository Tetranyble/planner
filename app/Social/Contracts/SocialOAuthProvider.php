<?php

namespace App\Social\Contracts;

use App\Social\Data\DiscoveredSocialAccount;
use App\Social\Data\OAuthGrant;

interface SocialOAuthProvider
{
    public function key(): string;

    public function authorizationUrl(string $state, string $redirectUri): string;

    public function exchange(string $code, string $redirectUri, ?string $state = null): OAuthGrant;

    /** @return list<DiscoveredSocialAccount> */
    public function discover(OAuthGrant $grant): array;

    /** @return array<string,array{mode:string,reason:string}> */
    public function capabilities(OAuthGrant $grant, DiscoveredSocialAccount $account): array;
}
