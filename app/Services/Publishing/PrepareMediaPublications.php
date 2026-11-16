<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Publishing\Media\MediaPublicationProtocols;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\MediaPublicationPreparation;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use App\Repository\Contracts\PublicationIntentRepositoryContract;
use App\Services\Content\AssessPublicationReadiness;
use App\Services\Content\StorageMediaInspector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** A source-complete media-preparation desk, never an uploader or remote dispatch engine. */
final class PrepareMediaPublications
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly PublicationIntentRepositoryContract $jobs,
        private readonly MediaPublicationPreparationRepositoryContract $repo,
        private readonly AssessPublicationReadiness $readiness,
        private readonly StorageMediaInspector $storage,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Media publishing preparation access denied.');
        }
    }

    public function list(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');

        return $this->repo->list($org, $brand, 100)->map(fn ($row) => self::present($row))->all();
    }

    public function show(User $actor, Organisation $org, Brand $brand, int $id): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        $row = $this->repo->find($org, $brand, $id);
        $data = self::present($row);
        $data['events'] = $row->events()->orderBy('id')->get(['action', 'from_state', 'to_state', 'created_at']);

        return $data;
    }

    public function prepare(User $actor, Organisation $org, Brand $brand, int $jobId): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($actor, $org, $brand, $jobId) {
            // Match the same org -> brand -> job lock ordering as the text worker.
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $lockedBrand, 'approval_request.update');
            $job = $this->jobs->findLocked($org, $lockedBrand, $jobId);
            if ($job->state !== 'AWAITING_USER' || $job->lease_owner !== null || (int) $job->attempt_count !== 0) {
                throw new ConflictHttpException('Media preparation requires an unattempted held job.');
            }
            $target = ContentTarget::query()->where('organisation_id', $org->id)
                ->where('brand_id', $lockedBrand->id)->whereKey($job->content_target_id)
                ->with(['version.mediaLinks.asset', 'account'])->firstOrFail();
            $account = $target->account;
            if (! $account || $account->status !== 'ACTIVE' || (int) $account->organisation_id !== (int) $org->id
                || (int) $account->brand_id !== (int) $lockedBrand->id) {
                throw new ConflictHttpException('Provider account is inactive or outside this brand.');
            }
            $provider = strtolower((string) $account->provider);
            if (! in_array($provider, MediaPublicationProtocols::PROVIDERS, true)) {
                throw new ConflictHttpException('This media protocol is not available for the selected provider.');
            }
            $links = $target->version->mediaLinks;
            if ($links->count() !== 1) {
                throw new ConflictHttpException('Only single-media workflows are supported by this protocol.');
            }
            $asset = $links->first()->asset;
            if (! $asset || ! $asset->storage_media_id || ! is_string($asset->sha256)
                || ! preg_match('/^[a-fA-F0-9]{64}$/D', (string) $asset->sha256)) {
                throw new ConflictHttpException('Canonical media identity is missing.');
            }
            $issues = MediaPublicationProtocols::validateAsset($provider, strtolower((string) $asset->mime_type),
                (int) $asset->byte_size, $links->count());
            if ($issues !== []) {
                throw new ConflictHttpException('Media protocol validation failed: '.implode(',', $issues));
            }
            $canonical = $this->storage->inspectCanonical($org, (int) $asset->storage_media_id);
            if (! is_string($canonical['sha256']) || ! hash_equals(strtolower((string) $asset->sha256),
                strtolower($canonical['sha256'])) || (int) $canonical['byte_size'] !== (int) $asset->byte_size
                || strtolower((string) $canonical['mime_type']) !== strtolower((string) $asset->mime_type)) {
                throw new ConflictHttpException('Canonical media has changed.');
            }
            $context = $this->jobs->context($org, $lockedBrand, $job);
            $readiness = $this->readiness->assess($actor, $org, $lockedBrand, $context['item_uuid'],
                $context['target_uuid'], (int) $job->approval_request_id);
            // Provider-specific automatic capability remains unverified until app review.
            // This is a preparation record, not authorization to execute the remote protocol.
            $soft = ['PUBLISHING_ENGINE_NOT_IMPLEMENTED', 'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED',
                'TARGET_AUTOMATIC_PUBLISHING_NOT_ENABLED'];
            $hard = array_values(array_diff($readiness['blockers'], $soft));
            if ($hard !== []) {
                throw new ConflictHttpException('Content/media approvals are not current: '.implode(',', $hard));
            }
            $approval = ApprovalRequest::query()->where('organisation_id', $org->id)
                ->where('brand_id', $lockedBrand->id)->whereKey($job->approval_request_id)->firstOrFail();
            $manifest = MediaPublicationProtocols::plan($provider, strtolower((string) $asset->mime_type));
            $manifest['provider'] = $provider;
            $manifest['canonical_media_id'] = (int) $asset->storage_media_id;
            $manifest['media_mime'] = (string) $asset->mime_type;
            $manifest['media_size'] = (int) $asset->byte_size;
            $manifest['remote_dispatch_allowed'] = false;
            $blocker = 'PROVIDER_MEDIA_DELIVERY_NOT_RELEASED';
            $existing = $this->repo->existing($org, $lockedBrand, $job);
            if ($existing) {
                if ($existing->state !== 'PREPARED' || (int) $existing->media_asset_id !== (int) $asset->id
                    || ! hash_equals((string) $existing->media_sha256, (string) $asset->sha256)
                    || ! hash_equals((string) $existing->approval_payload_hash, (string) $approval->payload_hash)) {
                    throw new ConflictHttpException('Existing media preparation is stale or was aborted. Create a new held plan after editorial review.');
                }

                return self::present($existing);
            }

            return self::present($this->repo->create($org, $lockedBrand, $job, $asset, $approval, $actor, $manifest, $blocker));
        }, 3);
    }

    public function abort(User $actor, Organisation $org, Brand $brand, int $id, int $expectedVersion): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($actor, $org, $brand, $id, $expectedVersion) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $lockedBrand, 'approval_request.update');
            $row = $this->repo->find($org, $lockedBrand, $id, true);
            if ($row->state !== 'PREPARED' || $row->version !== $expectedVersion) {
                throw new ConflictHttpException('Preparation changed or is no longer abortable.');
            }
            $this->repo->abort($row, $actor);

            return self::present($row);
        }, 3);
    }

    private static function present(MediaPublicationPreparation $row): array
    {
        $manifest = $row->protocol_manifest;

        // Never disclose immutable hashes, asset storage keys or any signed delivery URL.
        return ['id' => (int) $row->id, 'uuid' => (string) $row->uuid, 'publication_job_id' => (int) $row->publication_job_id,
            'provider' => (string) $row->provider, 'protocol' => (string) $row->protocol,
            'state' => (string) $row->state, 'version' => (int) $row->version,
            'stages' => $manifest['stages'] ?? [], 'blocker_code' => $row->blocker_code,
            'remote_dispatch_allowed' => false, 'created_at' => $row->created_at?->toIso8601String()];
    }
}
