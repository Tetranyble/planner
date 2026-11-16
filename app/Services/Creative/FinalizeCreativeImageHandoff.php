<?php

namespace App\Services\Creative;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Creative\CreativeImageFingerprint;
use App\Enums\Content\MediaSourceType;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;
use App\Services\Content\ManageContentStudio;
use App\Services\Content\StorageMediaInspector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** An operator may claim a reviewed PNG only after the package-owned upload becomes safe. */
final class FinalizeCreativeImageHandoff
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly CreativeImageHandoffRepositoryContract $handoffs,
        private readonly StorageMediaInspector $storage,
        private readonly ManageContentStudio $studio,
        private readonly AuditEventRepositoryContract $audit,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! $this->guard->allows($actor, $brand, 'media_asset.store')) {
            throw new AuthorizationException('Brand creative media permission denied.');
        }
    }

    /** @return array{handoff_uuid:string,media_asset_uuid:string,storage_media_id:int,linked:bool} */
    public function link(User $actor, Organisation $org, Brand $brand, string $handoffUuid, int $storageMediaId, bool $humanReviewed): array
    {
        $this->authorize($actor, $org, $brand);
        if (! $humanReviewed) {
            throw ValidationException::withMessages(['reviewed' => 'An operator must explicitly confirm editorial image review.']);
        }
        if (! config('planner.creative_image_enabled', false)) {
            throw new AuthorizationException('Creative handoffs are disabled by deployment policy.');
        }

        // Storage owns uploading, malware checks, and object integrity; this never writes file bytes.
        return DB::transaction(function () use ($actor, $org, $brand, $handoffUuid, $storageMediaId): array {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            Brand::query()->whereKey($brand->id)->where('organisation_id', $org->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $brand);
            $ticket = $this->handoffs->lockOwned($actor, $org, $brand, $handoffUuid);

            if ($ticket->state === 'LINKED') {
                if ((int) $ticket->storage_media_id !== $storageMediaId) {
                    throw new ConflictHttpException('Creative claim was already linked to a different file.');
                }
                $asset = MediaAsset::query()->whereKey($ticket->media_asset_id)
                    ->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                    ->where('storage_media_id', $storageMediaId)->firstOrFail();
                // Recheck canonical safety on idempotent retrieval: an asset can later be quarantined.
                $this->storage->inspect($org, $actor, $storageMediaId);

                return $this->result($ticket->uuid, $asset, $storageMediaId);
            }
            if ($ticket->state !== 'AWAITING_UPLOAD' || $ticket->expires_at->isPast()) {
                throw new ConflictHttpException('Creative upload handoff has expired or is unavailable. Generate a new one.');
            }
            // inspect() requires this very actor to have uploaded media to this organisation.
            $verified = $this->storage->inspect($org, $actor, $storageMediaId);
            if (! CreativeImageFingerprint::matches([
                'sha256' => $ticket->sha256, 'byte_size' => $ticket->byte_size,
                'width' => $ticket->width, 'height' => $ticket->height,
                'mime_type' => $ticket->mime_type,
            ], $verified)) {
                throw ValidationException::withMessages(['storage_media_id' => 'The verified Storage file does not exactly match this generated image.']);
            }
            // The normal media path supplies the second permission check, repository scope and audit.
            $imported = $this->studio->linkMedia($actor, $org, $brand, $storageMediaId);
            $asset = MediaAsset::query()->where('uuid', $imported['uuid'])
                ->where('organisation_id', $org->id)->where('brand_id', $brand->id)->firstOrFail();
            // Permanent, queryable AI provenance survives expiry of short-lived handoff metadata.
            $asset->forceFill(['source_type' => MediaSourceType::GENERATED->value])->save();
            $this->handoffs->markLinked($ticket, $asset, $storageMediaId);
            $this->audit->record($org, $actor, 'creative.image.handoff.linked', 'media_asset',
                $asset->uuid, $brand, ['handoff_uuid' => $ticket->uuid,
                    'sha256' => $ticket->sha256, 'storage_media_id' => $storageMediaId,
                    'operator_review_confirmed' => true]);

            return $this->result($ticket->uuid, $asset, $storageMediaId);
        }, 3);
    }

    private function result(string $ticketUuid, MediaAsset $asset, int $storageMediaId): array
    {
        return ['handoff_uuid' => $ticketUuid, 'media_asset_uuid' => $asset->uuid,
            'storage_media_id' => $storageMediaId, 'linked' => true];
    }
}
