<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentVersion;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Every lookup is bounded to organisation AND brand; no generic unscoped find(). */
interface ContentStudioRepositoryContract
{
    public function paginate(Organisation $organisation, Brand $brand, int $pageSize): LengthAwarePaginator;

    public function findItem(Organisation $organisation, Brand $brand, string $uuid, bool $lock = false): ContentItem;

    public function versions(Organisation $organisation, Brand $brand, ContentItem $item): Collection;

    public function targets(Organisation $organisation, Brand $brand, ContentItem $item): Collection;

    public function media(Organisation $organisation, Brand $brand): Collection;

    public function accounts(Organisation $organisation, Brand $brand): Collection;

    public function campaign(Organisation $organisation, Brand $brand, ?string $uuid): ?int;

    public function series(Organisation $organisation, Brand $brand, ?string $uuid): ?int;

    public function createItem(Organisation $organisation, Brand $brand, User $actor, array $input): ContentItem;

    public function latestVersionNumber(Organisation $organisation, Brand $brand, ContentItem $item): int;

    public function appendVersion(Organisation $organisation, Brand $brand, ContentItem $item, User $actor, int $number, string $text, array $mediaUuids, ?bool $aiGenerated = null, ?bool $includesSensitiveClaim = null): ContentVersion;

    public function importStorageMedia(Organisation $organisation, Brand $brand, User $actor, int $storageMediaId, array $details): MediaAsset;

    public function storageMediaAsset(Organisation $organisation, Brand $brand, int $storageMediaId): ?MediaAsset;

    public function findMediaForVersion(Organisation $organisation, Brand $brand, array $uuids): Collection;

    public function findAccount(Organisation $organisation, Brand $brand, string $uuid): SocialAccount;

    public function createTarget(Organisation $organisation, Brand $brand, ContentVersion $version, SocialAccount $account, User $actor, string $caption, string $mode = 'ASSISTED'): string;
}
