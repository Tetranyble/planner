<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Domain\Publishing\Media\GuardedMediaStep;
use App\Domain\Publishing\Media\LaravelMediaStepTransport;
use App\Domain\Publishing\Media\MediaDistributionUrlResolverContract;
use App\Domain\Publishing\Media\MediaPublicationProtocols;
use App\Domain\Publishing\Media\MediaReleaseRequirements;
use App\Domain\Publishing\Media\MediaStageTransition;
use App\Domain\Publishing\Media\MediaStepAuthorization;
use App\Domain\Publishing\Media\MediaStepRequest;
use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\MediaPublicationPreparation;
use App\Models\Organisation;
use App\Models\ProviderConnection;
use App\Models\ProviderConnectionCredential;
use App\Models\PublicationJob;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\MediaTikTokConsentRepositoryContract;
use App\Services\Content\AssessPublicationReadiness;
use App\Services\Content\StorageMediaInspector;
use Illuminate\Support\Facades\Crypt;

/**
 * Staged media worker: remote I/O outside transactions; current rights, approval and
 * canonical Storage are rechecked per step. All release switches OFF by default.
 * YouTube and TikTok explicitly blocked until their distinct release requirements exist.
 */
final class ProcessMediaPublicationRuns
{
    public function __construct(
        private readonly MediaPublicationRunRepositoryContract $runs,
        private readonly BrandPermissionGuard $guard,
        private readonly CurrentOrganisation $currentOrganisation,
        private readonly AssessPublicationReadiness $readiness,
        private readonly StorageMediaInspector $storage,
        private readonly MediaDistributionUrlResolverContract $distribution,
        private readonly MediaTikTokConsentRepositoryContract $consents,
    ) {}

    public static function enabled(): bool
    {
        return app()->environment('production')
            && ! (bool) config('planner.emergency_publish_stop', true)
            && (bool) config('planner.media_worker_enabled', false)
            && (bool) config('planner.media_dispatch_released', false);
    }

    public function process(int $limit = 15): array
    {
        $counts = ['disabled' => ! self::enabled(), 'claimed' => 0, 'held' => 0, 'attempted' => 0,
            'uncertain' => 0, 'awaiting_verification' => 0];
        if (! self::enabled()) {
            return $counts;
        }
        foreach ($this->runs->dueIds(max(1, min(50, $limit))) as $id) {
            $owner = bin2hex(random_bytes(24));
            $run = $this->runs->claim($id, $owner);
            if (! $run) {
                continue;
            }
            $counts['claimed']++;
            $attempt = null;
            try {
                $stage = $this->prepareStage($run);
                if (isset($stage['blocker'])) {
                    $this->runs->holdClaim($id, $owner, $stage['blocker']);
                    $counts['held']++;

                    continue;
                }
                $attempt = $this->runs->startStage($id, $owner);
                if (! $attempt) {
                    $this->runs->holdClaim($id, $owner, 'STAGE_FENCE_REJECTED');
                    $counts['held']++;

                    continue;
                }
                $fenced = $this->runs->stageIsFenced($id, $owner, (int) $attempt['attempt_id']);
                $proof = new MediaStepAuthorization(self::enabled(), $stage['provider_approved'],
                    $stage['approval_current'], $stage['canonical_verified'],
                    $stage['consent_verified'], $fenced, $fenced && ! MediaStageTransition::isProbe($stage['operation']));
                // Read-only status probes may repeat with a bounded counter; a repeated
                // mutating step can never create another STARTED attempt.
                if (MediaStageTransition::isProbe($stage['operation'])) {
                    $proof = new MediaStepAuthorization(self::enabled(), $stage['provider_approved'],
                        $stage['approval_current'], $stage['canonical_verified'], true, $fenced, $fenced);
                }
                $outcome = (new GuardedMediaStep(new LaravelMediaStepTransport))
                    ->execute($stage['request'], $proof, $stage['token']);
                $counts['attempted']++;
                if ($this->runs->finishStage($id, $owner, (int) $attempt['attempt_id'], $outcome)) {
                    if (($outcome['state'] ?? null) === 'UNCERTAIN') {
                        $counts['uncertain']++;
                    }
                    if (($outcome['state'] ?? null) === 'PUBLISHED') {
                        $counts['awaiting_verification']++;
                    }
                }
            } catch (\Throwable) {
                // Never leak provider payloads, credentials or Storage URL in logs/events.
                if ($attempt) {
                    $this->runs->finishStage($id, $owner, (int) $attempt['attempt_id'],
                        ['state' => 'UNCERTAIN', 'code' => 'MEDIA_WORKER_EXCEPTION']);
                    $counts['uncertain']++;
                } else {
                    $this->runs->holdClaim($id, $owner, 'MEDIA_PREFLIGHT_FAILED');
                    $counts['held']++;
                }
            }
        }

        return $counts;
    }

    private function prepareStage($run): array
    {
        // Restrict the first released driver to Instagram. Other providers require
        // creator-privacy consent or a true Storage streaming session.
        if (! in_array($run->provider, ['instagram', 'tiktok'], true)) {
            return ['blocker' => 'PROVIDER_STAGE_DRIVER_NOT_RELEASED'];
        }
        if (! (bool) config('planner.media_entitlements.'.$run->provider, false)
            || ! (bool) config('planner.media_delivery_origin_verified', false)) {
            return ['blocker' => 'PROVIDER_MEDIA_ENTITLEMENT_UNVERIFIED'];
        }
        $org = Organisation::query()->whereKey($run->organisation_id)->first();
        $brand = Brand::query()->where('organisation_id', $run->organisation_id)->whereKey($run->brand_id)->first();
        $actor = User::query()->whereKey($run->armed_by_user_id)->first();
        if (! $org || ! $brand || ! $actor) {
            return ['blocker' => 'ARMING_CONTEXT_UNAVAILABLE'];
        }

        return $this->currentOrganisation->within($actor, (int) $org->id,
            fn () => $this->stageForActor($run, $actor, $org, $brand));
    }

    private function stageForActor($run, User $actor, Organisation $org, Brand $brand): array
    {
        if (! $this->guard->allows($actor, $brand, 'approval_request.update')
            || ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            return ['blocker' => 'ARMING_PERMISSION_REVOKED'];
        }
        $prep = MediaPublicationPreparation::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereKey($run->media_publication_preparation_id)->first();
        $job = PublicationJob::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereKey($run->publication_job_id)->first();
        if (! $prep || ! $job || $prep->state !== 'PREPARED' || $job->state !== 'AWAITING_USER'
            || $job->run_at->isFuture() || (int) $job->attempt_count !== 0) {
            return ['blocker' => 'APPROVED_PUBLICATION_PLAN_NOT_DUE'];
        }
        $target = ContentTarget::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($job->content_target_id)->with(['version.mediaLinks.asset', 'account'])->first();
        if (! $target || ! $target->account || $target->account->status !== 'ACTIVE'
            || strtolower((string) $target->account->provider) !== $run->provider
            || $target->version->mediaLinks->count() !== 1) {
            return ['blocker' => 'MEDIA_TARGET_INVALIDATED'];
        }
        if ($target->publishing_mode !== 'AUTOMATIC') {
            return ['blocker' => 'TARGET_NOT_APPROVED_FOR_AUTOMATIC_PUBLISHING'];
        }
        $asset = $target->version->mediaLinks->first()->asset;
        if (! $asset || (int) $prep->media_asset_id !== (int) $asset->id
            || ! hash_equals((string) $prep->media_sha256, (string) $asset->sha256)) {
            return ['blocker' => 'MEDIA_EVIDENCE_INVALIDATED'];
        }
        $canonical = $this->storage->inspectCanonical($org, (int) $asset->storage_media_id);
        if (! is_string($canonical['sha256']) || ! hash_equals(strtolower((string) $asset->sha256),
            strtolower((string) $canonical['sha256'])) || (int) $asset->byte_size !== (int) $canonical['byte_size']
            || strtolower((string) $asset->mime_type) !== strtolower((string) $canonical['mime_type'])) {
            return ['blocker' => 'CANONICAL_STORAGE_CHANGED'];
        }
        $approval = ApprovalRequest::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereKey($job->approval_request_id)->first();
        if (! $approval || $approval->status !== 'APPROVED'
            || ! hash_equals((string) $prep->approval_payload_hash, (string) $approval->payload_hash)) {
            return ['blocker' => 'CONTENT_APPROVAL_CHANGED'];
        }
        // The full readiness assessor checks active policy, quorum, brand, and media provenance.
        $item = ContentItem::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->whereKey($target->version->content_item_id)->first();
        if (! $item) {
            return ['blocker' => 'CONTENT_ITEM_NOT_FOUND'];
        }
        $check = $this->readiness->assess($actor, $org, $brand, (string) $item->uuid,
            (string) $target->uuid, (int) $approval->id);
        $soft = ['PUBLISHING_ENGINE_NOT_IMPLEMENTED', 'PROVIDER_PUBLISH_CAPABILITY_NOT_VERIFIED',
            'TARGET_AUTOMATIC_PUBLISHING_NOT_ENABLED'];
        if (array_diff($check['blockers'], $soft) !== []) {
            return ['blocker' => 'PUBLISHING_READINESS_INVALIDATED'];
        }
        $stage = $run->next_operation;
        $account = $target->account;
        if ($run->provider === 'instagram'
            && ! preg_match('/^[0-9]{3,30}$/D', (string) $account->external_account_id)) {
            return ['blocker' => 'INVALID_INSTAGRAM_ACTOR_ID'];
        }
        $token = $this->credential($org, $account);
        $version = (string) config('social_integrations.meta_graph_version', 'v27.0');
        if ($run->provider === 'instagram' && ! preg_match('/^v[0-9]+\.[0-9]+$/D', $version)) {
            return ['blocker' => 'INVALID_GRAPH_VERSION'];
        }
        $creatorConsent = null;
        if ($run->provider === 'tiktok') {
            $consent = $this->consents->find($org, $brand, $run);
            if (! $consent || ! $consent->verified_at
                // Fresh creator options are mandatory at the mutating publish stage.
                // A provider may take hours to process a previously accepted upload;
                // read-only verification must continue with the bound consent evidence.
                || ($stage === 'INITIALIZE_VIDEO' && $consent->verified_at->lessThan(now()->subMinutes(10)))
                || ! hash_equals((string) $prep->approval_payload_hash, (string) $consent->approval_payload_hash)
                || ! hash_equals((string) $prep->media_sha256, (string) $consent->media_sha256)
                || ! hash_equals(hash_hmac('sha256', $token, (string) config('app.key')), (string) $consent->grant_fingerprint)) {
                return ['blocker' => 'TIKTOK_CREATOR_CONSENT_INVALIDATED'];
            }
            $creatorConsent = $consent->privacy_selection;
            $creatorConsent['allowed_privacy_levels'] = $consent->verified_creator_options['privacy_levels'] ?? [];
            $creatorConsent['creator_options_checked'] = true;
        }
        if ($stage === 'CREATE_CONTAINER' || $stage === 'INITIALIZE_VIDEO') {
            $origin = $this->distribution->resolve($org, $asset);
            $hosts = array_filter(array_map('trim', explode(',', (string) config('planner.media_distribution_hosts', ''))));
            $spec = MediaPublicationProtocols::initRequest($run->provider, (string) $account->external_account_id,
                strtolower((string) $asset->mime_type), (string) $target->platform_caption,
                $origin, $hosts, $creatorConsent);
        } elseif ($stage === 'CHECK_CONTAINER_STATUS' || $stage === 'FETCH_PUBLISH_STATUS') {
            $spec = MediaPublicationProtocols::statusRequest($run->provider, (string) $run->provider_operation_reference);
        } elseif ($stage === 'VERIFY_MEDIA_RECEIPT' && $run->provider === 'instagram') {
            $spec = MediaPublicationProtocols::verifyRequest('instagram', (string) $run->verified_post_reference);
        } elseif ($stage === 'VERIFY_VIDEO_RECEIPT' && $run->provider === 'tiktok') {
            $spec = MediaPublicationProtocols::verifyRequest('tiktok', (string) $run->provider_operation_reference);
        } elseif ($stage === 'PUBLISH_CONTAINER' && $run->provider === 'instagram') {
            $spec = MediaPublicationProtocols::finalizeInstagram((string) $account->external_account_id,
                (string) $run->provider_operation_reference);
        } else {
            return ['blocker' => 'UNSUPPORTED_MEDIA_STAGE'];
        }
        $path = str_replace('{graph_version}', $version, $spec['path']);
        $query = isset($spec['query']) ? ('?'.http_build_query($spec['query'], '', '&', PHP_QUERY_RFC3986)) : '';
        $request = new MediaStepRequest($run->provider, $stage, $spec['method'],
            'https://'.$spec['host'].$path.$query, $spec['body'] ?? [], $spec['headers'] ?? []);

        return ['request' => $request, 'token' => $token, 'operation' => $stage,
            'provider_approved' => true, 'approval_current' => true, 'canonical_verified' => true,
            'consent_verified' => true];
    }

    private function credential(Organisation $org, $account): string
    {
        $connection = ProviderConnection::query()->where('organisation_id', $org->id)
            ->where('provider', $account->provider)->whereKey($account->provider_connection_id)
            ->where('status', 'CONNECTED')->first();
        if (! $connection || ! $connection->token_expires_at || $connection->token_expires_at->lessThanOrEqualTo(now()->addMinutes(3))) {
            throw new \DomainException('Media credential expired.');
        }
        $scopes = is_array($connection->scopes) ? $connection->scopes : [];
        $required = MediaReleaseRequirements::forProvider((string) $account->provider);
        foreach ($required as $scope) {
            if (! in_array($scope, $scopes, true)) {
                throw new \DomainException('Media publishing scope not granted.');
            }
        }
        $row = ProviderConnectionCredential::query()->where('organisation_id',$org->id)
            ->where('provider_connection_id',$connection->id)->first();
        if (! $row?->access_token_ciphertext) {
            throw new \DomainException('Media credential unavailable.');
        }
        $token = Crypt::decryptString($row->access_token_ciphertext);
        if ($token === '' || strlen($token) > 8192) {
            throw new \DomainException('Media credential invalid.');
        }

        return $token;
    }
}
