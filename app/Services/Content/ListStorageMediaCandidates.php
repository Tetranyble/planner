<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\StorageMediaCandidateRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Browse existing package-owned media without implementing another uploader or issuing public URLs. */
final class ListStorageMediaCandidates
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly StorageMediaCandidateRepositoryContract $candidates,
        private readonly StorageMediaInspector $inspector) {}

    public function list(User $actor, Organisation $org, Brand $brand, int $beforeId = 0): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! $this->guard->allows($actor, $brand, 'media_asset.index')) {
            throw new AuthorizationException('Media library permission denied.');
        }
        $rows = $this->candidates->candidates($org, $actor, max(0, $beforeId), 30);
        $usable = [];
        foreach ($rows as $media) {
            try {
                $proof = $this->inspector->inspect($org, $actor, (int) $media->id);
                $usable[] = ['storage_media_id' => (int) $media->id,
                    'file_name' => $proof['file_name'], 'mime_type' => $proof['mime_type'],
                    'byte_size' => $proof['byte_size'],
                    'sha256' => strtolower((string) ($proof['sha256'] ?? '')),
                    'width' => $proof['width'], 'height' => $proof['height'],
                    'safe_to_link' => true];
            } catch (ValidationException) {
                // A quarantined or pending file must never appear linkable.
            }
        }

        return ['items' => $usable, 'next_before_id' => $rows->count() === 30 ? (int) $rows->last()->id : null,
            'storage_owned' => true, 'upload_available_here' => false];
    }
}
