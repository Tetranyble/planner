<?php

namespace App\Services\Publishing;

use App\Authorization\BrandPermissionGuard;
use App\Domain\Publishing\YouTube\CanonicalVideoChunkSourceContract;
use App\Domain\Publishing\YouTube\UnavailableVideoChunkSource;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\PublicationJob;
use App\Models\User;
use App\Models\YouTubeResumableTransfer;
use App\Repository\Contracts\YouTubeTransferRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ManageYouTubeTransfers
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly YouTubeTransferRepositoryContract $transfers,
        private readonly CanonicalVideoChunkSourceContract $source) {}

    private function authorize(User $actor, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($actor, $brand, $permission)) {
            throw new AuthorizationException('YouTube media transfer access denied.');
        }
    }

    public static function present($row): array
    {
        return ['id' => (int) $row->id, 'media_publication_run_id' => (int) $row->media_publication_run_id,
            'state' => (string) $row->state, 'next_action' => (string) $row->next_action,
            'committed_bytes' => (int) $row->committed_bytes, 'total_bytes' => (int) $row->total_bytes,
            'version' => (int) $row->version, 'poll_count' => (int) $row->poll_count,
            'blocker_code' => $row->blocker_code, 'has_session' => $row->session_url_ciphertext !== null,
            'has_video_receipt' => $row->video_id_ciphertext !== null, 'remote_publishing_enabled' => false];
    }

    public function list(User $actor, Organisation $org, Brand $brand): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');

        return $this->transfers->list($org, $brand, 100)->map(fn ($r) => self::present($r))->all();
    }

    public function show(User $actor, Organisation $org, Brand $brand, int $id): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.index');
        $row = $this->transfers->find($org, $brand, $id);
        $out = self::present($row);
        $out['attempts'] = $row->attempts()->orderBy('id')->get(['sequence', 'action', 'offset_bytes', 'state', 'outcome_code', 'created_at']);
        $out['events'] = $row->events()->orderBy('id')->get(['action', 'from_state', 'to_state', 'safe_details', 'created_at']);

        return $out;
    }

    public function create(User $actor, Organisation $org, Brand $brand, int $runId): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($actor, $org, $brand, $runId) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $brand, 'approval_request.update');
            $run = MediaPublicationRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                ->whereKey($runId)->lockForUpdate()->firstOrFail();
            $prep = MediaPublicationPreparation::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->whereKey($run->media_publication_preparation_id)->firstOrFail();
            $job = PublicationJob::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->whereKey($run->publication_job_id)->lockForUpdate()->firstOrFail();
            if ($run->state !== 'HELD' || $run->provider !== 'youtube' || $prep->state !== 'PREPARED'
                || $job->state !== 'AWAITING_USER' || (int) $job->attempt_count !== 0) {
                throw new ConflictHttpException('A current, unattempted YouTube preparation is required.');
            }
            $asset = MediaAsset::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->whereKey($prep->media_asset_id)->firstOrFail();
            if (! hash_equals((string) $prep->media_sha256, (string) $asset->sha256)) {
                throw new ConflictHttpException('Canonical media digest changed.');
            }

            return self::present($this->transfers->create($org, $brand, $run, $asset, $actor));
        }, 3);
    }

    public function arm(User $actor, Organisation $org, Brand $brand, int $id, int $version): array
    {
        $this->authorize($actor, $org, $brand, 'approval_request.update');
        if ((bool) config('planner.emergency_publish_stop', true)
            || ! (bool) config('planner.media_worker_enabled', false)
            || ! (bool) config('planner.youtube_resumable_enabled', false)
            || ! (bool) config('planner.media_entitlements.youtube', false)
            || $this->source instanceof UnavailableVideoChunkSource) {
            throw new ConflictHttpException('YouTube provider permission or validated Storage byte reader is unavailable.');
        }

        return DB::transaction(function () use ($actor, $org, $brand, $id, $version) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $org, $brand, 'approval_request.update');
            $row = $this->transfers->find($org, $brand, $id);
            // Lock and re-read the exact row; competing operators must not both arm it.
            $row = YouTubeResumableTransfer::query()->where('organisation_id',$org->id)
                ->where('brand_id',$brand->id)->whereKey($row->id)->lockForUpdate()->firstOrFail();

            return self::present($this->transfers->arm($row,$actor,$version));
        }, 3);
    }
}
