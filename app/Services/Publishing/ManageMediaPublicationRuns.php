<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\PublicationJob;
use App\Models\User;
use App\Repository\Contracts\MediaPublicationPreparationRepositoryContract;
use App\Repository\Contracts\MediaPublicationRunRepositoryContract;
use App\Repository\Contracts\MediaTikTokConsentRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** User-facing source of truth for staged media operations. Does not accept remote receipts from callers. */
final class ManageMediaPublicationRuns
{
    public function __construct(
        private readonly BrandPermissionGuard $guard,
        private readonly MediaPublicationPreparationRepositoryContract $preparations,
        private readonly MediaPublicationRunRepositoryContract $runs,
        private readonly MediaTikTokConsentRepositoryContract $consents,
    ) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('Media execution authorization denied.');
        }
    }

    public function list(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');

        return $this->runs->list($org, $brand, 100)->map(fn ($row) => self::present($row))->all();
    }

    public function show(User $actor, Organisation $org, Brand $brand, int $id): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        $run = $this->runs->find($org, $brand, $id);
        $output = self::present($run);
        $output['events'] = $run->events()->orderBy('id')->get(['action', 'from_state', 'to_state', 'details', 'created_at']);
        $output['attempts'] = $run->attempts()->orderBy('stage_sequence')->get([
            'stage_sequence', 'operation', 'state', 'outcome_code', 'started_at', 'finished_at', 'created_at']);

        // No encrypted provider reference, access credential or signed Storage URL in JSON.
        return $output;
    }

    public function create(User $actor, Organisation $org, Brand $brand, int $preparationId): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($actor, $org, $brand, $preparationId) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $lockedBrand, 'approval_request.update');
            $prep = $this->preparations->find($org, $lockedBrand, $preparationId, true);
            $job = PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                ->whereKey($prep->publication_job_id)->lockForUpdate()->firstOrFail();
            if ($prep->state !== 'PREPARED' || $job->state !== 'AWAITING_USER'
                || $job->lease_owner !== null || (int) $job->attempt_count !== 0) {
                throw new ConflictHttpException('Media preparation or publication plan is no longer held.');
            }

            return self::present($this->runs->create($org, $lockedBrand, $prep, $actor));
        }, 3);
    }

    public function arm(User $actor, Organisation $org, Brand $brand, int $runId, int $expectedVersion): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');
        if (! (bool) config('planner.media_worker_enabled', false)) {
            throw new ConflictHttpException('Media execution is disabled by deployment policy.');
        }

        return DB::transaction(function () use ($actor, $org, $brand, $runId, $expectedVersion) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $lockedBrand, 'approval_request.update');
            $run = $this->runs->find($org, $lockedBrand, $runId, true);
            $prep = $this->preparations->find($org, $lockedBrand, (int) $run->media_publication_preparation_id, true);
            $job = PublicationJob::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                ->whereKey($run->publication_job_id)->lockForUpdate()->firstOrFail();
            $target = ContentTarget::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->whereKey($job->content_target_id)->firstOrFail();
            if ($target->publishing_mode !== 'AUTOMATIC') {
                throw new ConflictHttpException('Media target is not authorized for automatic publishing.');
            }
            if ($prep->state !== 'PREPARED' || $job->state !== 'AWAITING_USER' || (int) $job->attempt_count !== 0) {
                throw new ConflictHttpException('Media preparation is no longer eligible.');
            }
            if ($run->provider === 'youtube') {
                throw new ConflictHttpException('YouTube requires a verified resumable Storage uploader.');
            }
            if ($run->provider === 'tiktok') {
                $consent = $this->consents->find($org, $lockedBrand, $run);
                if (! $consent || ! $consent->verified_at || $consent->verified_at->lessThan(now()->subMinutes(10))
                    || ! hash_equals((string) $prep->approval_payload_hash, (string) $consent->approval_payload_hash)
                    || ! hash_equals((string) $prep->media_sha256, (string) $consent->media_sha256)) {
                    throw new ConflictHttpException('Fresh, exact-version TikTok creator options and user consent are required.');
                }
            }
            if (! (bool) config('planner.media_entitlements.'.$run->provider, false)
                || ! (bool) config('planner.media_delivery_origin_verified', false)) {
                throw new ConflictHttpException('Provider app review and Storage distribution must be verified before arming.');
            }

            return self::present($this->runs->arm($run, $actor, $expectedVersion));
        }, 3);
    }

    public function cancel(User $actor, Organisation $org, Brand $brand, int $runId, int $expectedVersion): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($actor, $org, $brand, $runId, $expectedVersion) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $lockedBrand = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $lockedBrand, 'approval_request.update');
            $run = $this->runs->find($org, $lockedBrand, $runId, true);

            return self::present($this->runs->cancel($run, $actor, $expectedVersion));
        }, 3);
    }

    public static function present(MediaPublicationRun $run): array
    {
        return ['id' => (int) $run->id, 'preparation_id' => (int) $run->media_publication_preparation_id,
            'publication_job_id' => (int) $run->publication_job_id, 'provider' => (string) $run->provider,
            'state' => (string) $run->state, 'stage' => (string) $run->next_operation,
            'version' => (int) $run->version, 'stage_sequence' => (int) $run->stage_sequence,
            'poll_count' => (int) $run->poll_count, 'blocker_code' => $run->blocker_code,
            'has_provider_reference' => $run->provider_operation_reference !== null,
            'next_check_at' => $run->next_check_at?->toIso8601String(),
            'remote_delivery_enabled' => false];
    }
}
