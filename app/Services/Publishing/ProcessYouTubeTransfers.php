<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Publishing\Media\MediaReleaseRequirements;
use App\Domain\Publishing\YouTube\CanonicalVideoChunkSourceContract;
use App\Domain\Publishing\YouTube\UnavailableVideoChunkSource;
use App\Domain\Publishing\YouTube\YouTubeUploadTransportContract;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\MediaAsset;
use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\PublicationJob;
use App\Models\User;
use App\Repository\Contracts\YouTubeTransferRepositoryContract;
use App\Services\Content\AssessPublicationReadiness;
use App\Services\Content\StorageMediaInspector;
use Illuminate\Support\Facades\Crypt;

/** Only a deliberately released, reviewed Storage-backed application can execute these stages. */
final class ProcessYouTubeTransfers
{
    public function __construct(
        private readonly YouTubeTransferRepositoryContract $transfers,
        private readonly CurrentOrganisation $currentOrganisation,
        private readonly BrandPermissionGuard $guard,
        private readonly AssessPublicationReadiness $readiness,
        private readonly StorageMediaInspector $storage,
        private readonly CanonicalVideoChunkSourceContract $source,
        private readonly YouTubeUploadTransportContract $transport,
    ) {}

    public static function enabled(): bool
    {
        return app()->environment('production') && ! (bool) config('planner.emergency_publish_stop', true)
            && (bool) config('planner.media_worker_enabled', false)
            && (bool) config('planner.media_dispatch_released', false)
            && (bool) config('planner.youtube_resumable_enabled', false)
            && (bool) config('planner.media_entitlements.youtube', false);
    }

    public function process(int $limit = 10): array
    {
        $counts = ['disabled' => ! self::enabled() || $this->source instanceof UnavailableVideoChunkSource, 'claimed' => 0, 'held' => 0, 'attempted' => 0, 'uncertain' => 0];
        if (! self::enabled() || $this->source instanceof UnavailableVideoChunkSource) {
            return $counts;
        }
        foreach ($this->transfers->dueIds(max(1, min(30, $limit))) as $id) {
            $owner = bin2hex(random_bytes(24));
            $row = $this->transfers->claim((int) $id, $owner);
            if (! $row) {
                continue;
            }
            $counts['claimed']++;
            $attempt = null;
            try {
                $context = $this->preflight($row);
                if (isset($context['blocker'])) {
                    $this->transfers->hold((int) $id, $owner, $context['blocker']);
                    $counts['held']++;

                    continue;
                }
                $attempt = $this->transfers->start((int) $id, $owner);
                if (! $attempt) {
                    $this->transfers->hold((int) $id, $owner, 'YOUTUBE_STAGE_FENCE_REJECTED');
                    $counts['held']++;

                    continue;
                }
                $outcome = $this->transport->execute($attempt['action'], $context['token'],
                    $row->session_url_ciphertext, $row->video_id_ciphertext, $context['organisation'],
                    $context['asset'], (int) $attempt['offset'], $context['caption'], $context['channel']);
                $counts['attempted']++;
                if ($this->transfers->finish((int) $id, $owner, (int) $attempt['attempt_id'], $outcome)
                    && in_array($outcome['state'] ?? null, ['UNCERTAIN', 'MANUAL_REQUIRED'], true)) {
                    $counts['uncertain']++;
                }
            } catch (\Throwable) {
                // No request body, URL, credential or raw provider response leaves this worker.
                if ($attempt) {
                    $this->transfers->finish((int) $id, $owner, (int) $attempt['attempt_id'],
                        ['state' => 'UNCERTAIN', 'code' => 'YOUTUBE_STAGE_EXCEPTION']);
                    $counts['uncertain']++;
                } else {
                    $this->transfers->hold((int) $id, $owner, 'YOUTUBE_PREFLIGHT_FAILED');
                    $counts['held']++;
                }
            }
        }

        return $counts;
    }

    private function preflight($row): array
    {
        $org = Organisation::query()->whereKey($row->organisation_id)->first();
        $brand = Brand::query()->where('organisation_id', $row->organisation_id)->whereKey($row->brand_id)->first();
        $actor = User::query()->whereKey($row->armed_by_user_id)->first();
        if (! $org || ! $brand || ! $actor) {
            return ['blocker' => 'YOUTUBE_CONTEXT_REVOKED'];
        }

        return $this->currentOrganisation->within($actor, (int) $org->id,
            fn () => $this->preflightWithin($row, $org, $brand, $actor));
    }

    private function preflightWithin($row, Organisation $org, Brand $brand, User $actor): array
    {
        if (! $this->guard->allows($actor, $brand, 'approval_request.update')
            || ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            return ['blocker' => 'YOUTUBE_PERMISSION_REVOKED'];
        }
        $run = MediaPublicationRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($row->media_publication_run_id)->first();
        if (! $run || $run->state !== 'ARMED' || $run->provider !== 'youtube' || $run->attempts()->exists()) {
            return ['blocker' => 'YOUTUBE_PARENT_RUN_CHANGED'];
        }
        $prep = MediaPublicationPreparation::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereKey($run->media_publication_preparation_id)->first();
        $job = PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($run->publication_job_id)->first();
        if (! $prep || ! $job || $prep->state !== 'PREPARED' || $job->state !== 'AWAITING_USER'
            || $job->run_at->isFuture() || (int) $job->attempt_count !== 0) {
            return ['blocker' => 'YOUTUBE_APPROVED_PLAN_NOT_DUE'];
        }
        $asset = MediaAsset::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($row->media_asset_id)->first();
        if (! $asset || (int) $prep->media_asset_id !== (int) $asset->id
            || ! hash_equals((string) $row->canonical_sha256, strtolower((string) $asset->sha256))
            || ! hash_equals((string) $prep->media_sha256, (string) $asset->sha256)
            || (int) $row->total_bytes !== (int) $asset->byte_size
            || ! hash_equals((string) $row->canonical_mime, strtolower((string) $asset->mime_type))) {
            return ['blocker' => 'YOUTUBE_CANONICAL_MEDIA_CHANGED'];
        }
        $canonical = $this->storage->inspectCanonical($org, (int) $asset->storage_media_id);
        if (! hash_equals((string) $row->canonical_sha256, strtolower((string) ($canonical['sha256'] ?? '')))
            || (int) ($canonical['byte_size'] ?? -1) !== (int) $row->total_bytes
            || ! hash_equals((string) $row->canonical_mime, strtolower((string) ($canonical['mime_type'] ?? '')))) {
            return ['blocker' => 'YOUTUBE_STORAGE_EVIDENCE_CHANGED'];
        }
        $approval = ApprovalRequest::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($job->approval_request_id)->first();
        if (! $approval || $approval->status !== 'APPROVED'
            || ! hash_equals((string) $approval->payload_hash, (string) $prep->approval_payload_hash)) {
            return ['blocker' => 'YOUTUBE_APPROVAL_REVOKED'];
        }
        $target = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($job->content_target_id)->with(['version.mediaLinks.asset', 'account'])->first();
        if (! $target || $target->publishing_mode !== 'AUTOMATIC' || ! $target->account
            || $target->account->status !== 'ACTIVE' || $target->account->provider !== 'youtube'
            || (int) $target->account->organisation_id !== (int) $org->id
            || (int) $target->account->brand_id !== (int) $brand->id
            || (int) $target->version->mediaLinks->count() !== 1
            || (int) $target->version->mediaLinks->first()->media_asset_id !== (int) $asset->id) {
            return ['blocker' => 'YOUTUBE_TARGET_INVALIDATED'];
        }
        $channel = (string) $target->account->external_account_id;
        if (! preg_match('/^[A-Za-z0-9_-]{4,128}$/D', $channel)) {
            return ['blocker' => 'YOUTUBE_CHANNEL_ID_INVALID'];
        }
        $item = ContentItem::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($target->version->content_item_id)->first();
        if (! $item) {
            return ['blocker' => 'YOUTUBE_CONTENT_NOT_FOUND'];
        }
        $check = $this->readiness->assess($actor, $org, $brand, (string) $item->uuid,
            (string) $target->uuid, (int) $approval->id);
        $soft = ['PUBLISHING_ENGINE_NOT_IMPLEMENTED', 'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED',
            'TARGET_AUTOMATIC_PUBLISHING_NOT_ENABLED'];
        if (array_diff($check['blockers'] ?? [], $soft) !== []) {
            return ['blocker' => 'YOUTUBE_PUBLICATION_READINESS_CHANGED'];
        }
        $connection = ProviderConnection::query()->where('organisation_id', $org->id)
            ->where('provider', 'youtube')->whereKey($target->account->provider_connection_id)
            ->where('status', 'CONNECTED')->first();
        if (! $connection || ! $connection->token_expires_at
            || $connection->token_expires_at->lessThanOrEqualTo(now()->addMinutes(3))) {
            return ['blocker' => 'YOUTUBE_TOKEN_EXPIRED'];
        }
        $scopes = is_array($connection->scopes) ? $connection->scopes : [];
        foreach (MediaReleaseRequirements::forProvider('youtube') as $scope) {
            if (! in_array($scope, $scopes, true)) {
                return ['blocker' => 'YOUTUBE_UPLOAD_SCOPE_NOT_GRANTED'];
            }
        }
        $credential = ProviderConnectionCredential::query()->where('organisation_id', $org->id)
            ->where('provider_connection_id', $connection->id)->first();
        if (! $credential?->access_token_ciphertext) {
            return ['blocker' => 'YOUTUBE_CREDENTIAL_UNAVAILABLE'];
        }
        $token = Crypt::decryptString($credential->access_token_ciphertext);
        if (! is_string($token) || $token === '') {
            return ['blocker' => 'YOUTUBE_CREDENTIAL_UNAVAILABLE'];
        }

        return ['organisation' => $org, 'asset' => $asset, 'token' => $token, 'caption' => (string) $target->platform_caption,
            'channel' => $channel];
    }
}
