<?php

namespace App\Services\Content;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Content\PlatformMediaPreflightRules;
use App\Domain\Content\PublicationReadinessRules;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\ContentApprovalRepositoryContract;
use App\Repository\Contracts\ContentStudioRepositoryContract;
use App\Repository\Contracts\PublicationReadinessRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

/** A diagnostic preview. This cannot create jobs, permission grants or publication tokens. */
final class AssessPublicationReadiness
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly ContentStudioRepositoryContract $studio,
        private readonly ContentApprovalRepositoryContract $approvals,
        private readonly PublicationReadinessRepositoryContract $connections,
        private readonly ManageContentApprovals $approvalService,
        private readonly StorageMediaInspector $media,
    ) {}

    public function assess(User $actor, Organisation $org, Brand $brand, string $itemUuid, string $targetUuid, int $requestId): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Brand review access denied.');
        }
        $item = $this->studio->findItem($org, $brand, $itemUuid);
        $request = $this->approvals->find($org, $brand, $item, $requestId);
        $target = $this->approvals->target($org, $brand, $item, $targetUuid);
        if ((int) $target->id !== (int) $request->content_target_id) {
            // Do not disclose cross-target approval existence.
            abort(404);
        }
        $policy = $this->approvals->activePolicy($org, $brand);
        $policyMatches = $policy !== null && (int) $policy->id === (int) $request->approval_policy_id;
        $snapshotMatches = false;
        $required = (int) $request->required_approvals;
        if ($policyMatches) {
            $fresh = $this->approvalService->currentSnapshot($org, $brand, $item, $target, $policy);
            $snapshotMatches = hash_equals((string) $request->payload_hash, $fresh['payload_hash'])
                && $required === $fresh['required_approvals']
                && (bool) ($request->policy_snapshot['forbid_self_approval'] ?? true) === $fresh['forbid_self_approval'];
        }
        $mediaVerified = true;
        $mediaMetadata = [];
        foreach ($target->version->mediaLinks as $link) {
            $mediaMetadata[] = [
                'mime_type' => $link->asset?->mime_type, 'byte_size' => (int) ($link->asset?->byte_size ?? 0),
                'width' => (int) ($link->asset?->width ?? 0), 'height' => (int) ($link->asset?->height ?? 0),
                'duration_ms' => (int) ($link->asset?->duration_ms ?? 0),
            ];
            $asset = $link->asset;
            if (! $asset || ! $asset->storage_media_id || ! $asset->sha256) {
                $mediaVerified = false;
                break;
            }
            try {
                $canonical = $this->media->inspectCanonical($org, (int) $asset->storage_media_id);
                if (! is_string($canonical['sha256']) || ! hash_equals((string) $asset->sha256, $canonical['sha256'])) {
                    $mediaVerified = false;
                    break;
                }
            } catch (Throwable) {
                $mediaVerified = false;
                break;
            }
        }
        $account = $target->account;
        $mediaPreflight = PlatformMediaPreflightRules::inspect((string) $account->provider, $mediaMetadata);
        $health = $this->connections->connectionIsHealthy($org, $brand, $account);
        $capability = $this->connections->hasVerifiedAutomaticPublishCapability($org, $brand, $account);
        $approvedBy = $request->decisions()->where('decision', 'APPROVE')->distinct()->count('approver_user_id');
        $blockers = PublicationReadinessRules::blockers(
            (string) $item->state,
            (int) $target->version->version_number,
            $this->studio->latestVersionNumber($org, $brand, $item),
            (string) $request->status,
            $request->expires_at === null || $request->expires_at->isPast(),
            $policyMatches,
            $snapshotMatches,
            (int) $approvedBy,
            $required,
            $account->status === 'ACTIVE',
            $health['connection_active'],
            $health['credential_valid'],
            $mediaVerified,
            $capability,
        );
        if (! $mediaPreflight['editorially_valid']) {
            $blockers[] = 'MEDIA_EDITORIAL_PREFLIGHT_FAILED';
        }
        // Readiness remains deliberately conservative; actual media delivery is not enabled.
        if ($target->publishing_mode !== 'AUTOMATIC') {
            $blockers[] = 'TARGET_AUTOMATIC_PUBLISHING_NOT_ENABLED';
        }
        $blockers[] = 'PUBLISHING_ENGINE_NOT_IMPLEMENTED';

        return [
            'item_uuid' => (string) $item->uuid,
            'target_uuid' => (string) $target->uuid,
            'approval_request_id' => (int) $request->id,
            'readiness' => 'BLOCKED',
            'can_schedule' => false,
            'can_publish' => false,
            'blockers' => array_values(array_unique($blockers)),
            'media_preflight' => $mediaPreflight,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
