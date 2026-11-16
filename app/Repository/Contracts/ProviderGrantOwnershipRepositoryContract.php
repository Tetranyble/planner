<?php

namespace App\Repository\Contracts;

use App\Models\ProviderConnection;

interface ProviderGrantOwnershipRepositoryContract
{
    public function linkGrant(ProviderConnection $connection, string $actorId): void;

    public function linkMeta(ProviderConnection $connection, string $appScopedActorId): void;

    public function markDetached(ProviderConnection $connection): void;

    /** @return list<int> */
    public function metaConnectionIds(string $appScopedActorId): array;
}
