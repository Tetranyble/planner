<?php

namespace App\Services\Content;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tetranyble\Storage\Modules\Media\Domain\Enums\MediaStatus;
use Tetranyble\Storage\Modules\Media\Infrastructure\Persistence\Eloquent\Models\Media;
use Tetranyble\Storage\Modules\Trust\Domain\Enums\VirusScanStatus;

/** No new uploader or filesystem adapter: Tetranyble Storage owns the physical media. */
final class StorageMediaInspector
{
    public function inspect(Organisation $organisation, User $actor, int $mediaId): array
    {
        // Initial import requires the actor to own the upload. Subsequent use
        // of an already linked brand asset still requires its canonical file to be safe.
        return $this->inspectCanonical($organisation, $mediaId, (int) $actor->id);
    }

    public function inspectCanonical(Organisation $organisation, int $mediaId, ?int $uploadedBy = null): array
    {
        $media = Media::query()->whereKey($mediaId)
            ->where('workspace_id', $organisation->id)
            ->when($uploadedBy !== null, fn ($q) => $q->where('uploaded_by', $uploadedBy))
            ->whereNull('deleted_at')->where('is_temporary', false)->first();
        $virusAllowed = [VirusScanStatus::CLEAN];
        if (! config('tetranyble-storage.trust.virus_scanning.enabled', false)) {
            $virusAllowed[] = VirusScanStatus::SKIPPED;
        }
        if (! $media || ! in_array($media->status, [MediaStatus::READY, MediaStatus::APPROVED, MediaStatus::DONE], true)
            || $media->quarantined_at !== null || $media->archived_at !== null
            || ! in_array($media->virus_scan_status, $virusAllowed, true)) {
            throw ValidationException::withMessages(['storage_media_id' => 'Media is unavailable, not owned by you, or has not passed safety checks.']);
        }

        return [
            'file_name' => (string) $media->original_name, 'mime_type' => (string) $media->mime_type,
            'byte_size' => (int) $media->size, 'sha256' => $media->sha256,
            'width' => $media->width, 'height' => $media->height,
        ];
    }
}
