<?php

namespace App\Services\Creative;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Creative\CreativeImageFingerprint;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandMemberRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tetranyble\Storage\Http\Adapters\LaravelIncomingFile;
use Tetranyble\Storage\Modules\Media\Application\UploadMedia;

/**
 * Opt-in package-owned ingestion of an unpublished PNG; never creates a Planner
 * MediaAsset, skips Storage's processing, or authorizes a publication.
 * UploadMedia v3.0.0 is the documented canonical application upload API.
 */
final class CanonicalCreativeImageUpload
{
    public function __construct(
        private readonly UploadMedia $uploader,
        private readonly BrandPermissionGuard $permissions,
        private readonly BrandMemberRepositoryContract $members,
    ) {}

    public static function isReleased(): bool
    {
        return config('planner.creative_image_enabled', false)
            && config('planner.creative_auto_storage_enabled', false)
            && config('planner.storage_v3_adapter_verified', false);
    }

    public function upload(User $actor, Organisation $org, Brand $brand, array $image): int
    {
        if (! self::isReleased()) {
            throw new AuthorizationException('Canonical creative ingestion is disabled until Storage verification.');
        }
        $role = $this->members->organisationMember($org, (int) $actor->id)?->role_name;
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! in_array($role, ['owner', 'manager', 'contributor'], true)
            || ! $this->permissions->allows($actor, $brand, 'media_asset.store')) {
            throw new AuthorizationException('Creative upload permission denied.');
        }
        $bytes = $image['bytes'] ?? null;
        if (! is_string($bytes) || strlen($bytes) > 16 * 1024 * 1024 || strlen($bytes) < 45
            || ! CreativeImageFingerprint::matches([
                'sha256' => $image['sha256'] ?? null,
                'byte_size' => strlen($bytes), 'width' => $image['width'] ?? null,
                'height' => $image['height'] ?? null, 'mime_type' => 'image/png',
            ], [
                'sha256' => hash('sha256', $bytes), 'byte_size' => strlen($bytes),
                'width' => $image['width'] ?? null, 'height' => $image['height'] ?? null,
                'mime_type' => 'image/png',
            ]) || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || ! ($dimensions = @getimagesizefromstring($bytes))
            || ($dimensions['mime'] ?? '') !== 'image/png'
            || (int) $dimensions[0] !== (int) ($image['width'] ?? 0)
            || (int) $dimensions[1] !== (int) ($image['height'] ?? 0)) {
            throw ValidationException::withMessages(['creative' => 'Generated image has an invalid fingerprint.']);
        }
        // A private, process-local staging file exists only while the package's
        // own uploader consumes it. No direct filesystem media object is created.
        $path = tempnam(sys_get_temp_dir(), 'planner-creative-');
        if ($path === false) {
            throw new \RuntimeException('Private staging unavailable.');
        }
        try {
            @chmod($path, 0600);
            if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new \RuntimeException('Private staging write was incomplete.');
            }
            $uploaded = new UploadedFile($path, 'planner-generated-'.$image['sha256'].'.png', 'image/png', null, true);
            $media = $this->uploader->uploadLibraryFiles(
                workspace: $org,
                uploadedFiles: [LaravelIncomingFile::fromUploadedFile($uploaded)],
                folderId: null,
                actor: $actor,
            );
            $id = $media[0]->id ?? null;
            if (! is_numeric($id) || (int) $id < 1) {
                throw new \UnexpectedValueException('Storage did not return a canonical media identifier.');
            }

            // Return only its opaque identifier. Safety, content trust and the
            // exact SHA-256 are revalidated at the human-review linkage boundary.
            return (int) $id;
        } finally {
            @unlink($path);
        }
    }
}
