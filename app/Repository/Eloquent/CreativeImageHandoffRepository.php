<?php

namespace App\Repository\Eloquent;

use App\Enums\Content\MediaSourceType;
use App\Models\Brand;
use App\Models\CreativeImageHandoff;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;

final class CreativeImageHandoffRepository implements CreativeImageHandoffRepositoryContract
{
    public function issue(User $actor, Organisation $org, Brand $brand, array $image, int $ttlMinutes): CreativeImageHandoff
    {
        $handoff = new CreativeImageHandoff;
        $handoff->forceFill([
            'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'actor_user_id' => $actor->id, 'sha256' => $image['sha256'],
            'byte_size' => strlen($image['bytes']), 'width' => $image['width'],
            'height' => $image['height'], 'mime_type' => 'image/png',
            'state' => 'AWAITING_UPLOAD', 'expires_at' => now()->addMinutes($ttlMinutes),
        ])->save();

        return $handoff;
    }

    public function lockOwned(User $actor, Organisation $org, Brand $brand, string $uuid): CreativeImageHandoff
    {
        return CreativeImageHandoff::query()
            ->where('uuid', $uuid)->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('actor_user_id', $actor->id)
            ->lockForUpdate()->firstOrFail();
    }

    public function generatedMediaIds(Organisation $org, Brand $brand, array $mediaAssetIds): array
    {
        if ($mediaAssetIds === []) {
            return [];
        }
        // A short-lived handoff can be pruned; the media record holds durable provenance.
        $byOrigin = MediaAsset::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereIn('id', $mediaAssetIds)
            ->where('source_type', MediaSourceType::GENERATED->value)
            ->pluck('id')->all();
        $byReceipt = CreativeImageHandoff::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('state', 'LINKED')
            ->whereIn('media_asset_id', $mediaAssetIds)->pluck('media_asset_id')->all();

        return array_values(array_unique(array_map('intval', [...$byOrigin, ...$byReceipt])));
    }

    public function markLinked(CreativeImageHandoff $handoff, MediaAsset $asset, int $storageMediaId): void
    {
        $handoff->forceFill(['state' => 'LINKED', 'storage_media_id' => $storageMediaId,
            'media_asset_id' => $asset->id, 'linked_at' => now()])->save();
    }
}
