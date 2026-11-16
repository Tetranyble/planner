<?php

namespace App\Services\Content;

use App\Models\MediaAsset;
use App\Models\Organisation;
use Tetranyble\Storage\Modules\Media\Infrastructure\Persistence\Eloquent\Models\Media;
use Tetranyble\Storage\Modules\Storage\Domain\Enums\Disk;

/** Read-only reference to package-owned safe canonical media, scoped to one organisation. */
final class CanonicalStorageMediaAccess
{
    public function __construct(private readonly StorageMediaInspector $inspector) {}

    public function find(Organisation $org, MediaAsset $asset): Media
    {
        if ((int) $asset->organisation_id !== (int) $org->id || ! $asset->storage_media_id) {
            throw new \DomainException('Canonical Storage asset does not belong to this workspace.');
        }
        $record = $this->inspector->inspectCanonical($org, (int) $asset->storage_media_id);
        $expectedHash = strtolower((string) $asset->sha256);
        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedHash)
            || ! hash_equals($expectedHash, strtolower((string) ($record['sha256'] ?? '')))
            || (int) ($record['byte_size'] ?? -1) !== (int) $asset->byte_size
            || ! hash_equals(strtolower((string) $asset->mime_type), strtolower((string) ($record['mime_type'] ?? '')))) {
            throw new \DomainException('Canonical Storage metadata changed.');
        }
        $media = Media::query()->whereKey($asset->storage_media_id)->where('workspace_id', $org->id)
            ->whereNull('deleted_at')->where('is_temporary', false)->firstOrFail();
        if (! $media->disk instanceof Disk || ! is_string($media->path) || $media->path === ''
            || str_contains($media->path, "\0") || str_contains($media->path, '://')
            || str_contains($media->path, '\\') || str_starts_with($media->path, '/')
            || preg_match('~(^|/)\.\.?(/|$)~', $media->path)) {
            throw new \DomainException('Canonical Storage object key is unavailable.');
        }

        return $media;
    }
}
