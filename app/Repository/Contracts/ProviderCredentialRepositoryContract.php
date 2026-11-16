<?php

namespace App\Repository\Contracts;

use App\Models\ProviderConnection;
use App\Social\Data\OAuthGrant;

interface ProviderCredentialRepositoryContract
{
    /** Persist encrypted secrets; never return plaintext to UI, agents, or audit logs. */
    /** @return bool Whether an encrypted refresh token is available after storing this grant. */
    public function store(ProviderConnection $connection, OAuthGrant $grant): bool;

    public function erase(ProviderConnection $connection): void;
}
