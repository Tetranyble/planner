<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\CreativeImageHandoff;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\User;

interface CreativeImageHandoffRepositoryContract
{
    /** Only checksum/provenance; no raw image bytes or provider secrets. */
    public function issue(User $actor, Organisation $org, Brand $brand, array $image, int $ttlMinutes): CreativeImageHandoff;

    /** Must be called under a transaction and locked org -> brand -> handoff. */
    public function lockOwned(User $actor, Organisation $org, Brand $brand, string $uuid): CreativeImageHandoff;

    /** Linked generated-image lineage for media disclosure; never guess from filename. */
    public function generatedMediaIds(Organisation $org, Brand $brand, array $mediaAssetIds): array;

    public function markLinked(CreativeImageHandoff $handoff, MediaAsset $asset, int $storageMediaId): void;
}
