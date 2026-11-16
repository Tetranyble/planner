<?php

namespace App\Repository\Contracts;

use App\Models\MetaDataDeletionRequest;
use DateTimeInterface;

interface MetaDataDeletionRepositoryContract
{
    public function accept(string $actorId, string $signedRequestDigest): MetaDataDeletionRequest;

    public function findPublic(string $code): ?MetaDataDeletionRequest;

    /** @return list<int> */
    public function dueIds(int $limit): array;

    public function process(int $requestId): void;

    public function fail(int $requestId, string $code): void;

    /** Reject stale OAuth flows started before a provider data deletion request. */
    public function blocksExistingOAuth(string $actorId, DateTimeInterface $oauthStartedAt): bool;
}
