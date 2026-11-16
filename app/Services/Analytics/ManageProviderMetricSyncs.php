<?php

namespace App\Services\Analytics;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PublishedPost;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Per-brand explicit sync of confirmed provider posts. Provider IDs are never supplied by clients. */
final class ManageProviderMetricSyncs
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly ProviderMetricSyncRepositoryContract $repo,
        private readonly AuditEventRepositoryContract $audit) {}

    private function authorize(User $user, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id || ! $this->guard->allows($user, $brand, $permission)) {
            throw new AuthorizationException('Provider metric sync access denied.');
        }
    }

    public function index(User $user, Organisation $org, Brand $brand): array
    {
        $this->authorize($user, $org, $brand, 'approval_request.index');

        return ['released' => (bool) config('planner.provider_metric_sync_enabled', false),
            'providers' => ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], 'x_released' => (bool) config('planner.x_metric_sync_enabled', false),
            'facebook_released' => (bool) config('planner.facebook_metric_sync_enabled', false),
            'tiktok_released' => (bool) config('planner.tiktok_metric_sync_enabled', false),
            'instagram_released' => (bool) config('planner.instagram_metric_sync_enabled', false), 'runs' => $this->repo->list($org, $brand, 50)->map(fn ($r) => [
                'id' => $r->id, 'post_id' => $r->published_post_id, 'provider' => $r->provider, 'state' => $r->state,
                'sync_day' => $r->sync_day->format('Y-m-d'), 'attempts' => $r->attempts,
                'outcome_code' => $r->outcome_code, 'completed_at' => $r->completed_at?->toIso8601String(),
            ])->all(),
            'published_posts' => $this->repo->recentPosts($org, $brand, 50)->map(fn ($p) => [
                'id' => $p->id, 'provider' => $p->provider, 'external_post_id' => $p->external_post_id,
                'published_at' => $p->published_at?->toIso8601String(),
            ])->all()];
    }

    public function queue(User $user, Organisation $org, Brand $brand, array $postIds): array
    {
        $this->authorize($user, $org, $brand, 'approval_request.update');
        if (! config('planner.provider_metric_sync_enabled', false)) {
            throw ValidationException::withMessages(['sync' => 'Read-only provider metric sync is disabled.']);
        }
        $ids = array_values(array_unique(array_map('intval', $postIds)));
        if ($ids === [] || count($ids) > 20 || count($ids) !== count($postIds)) {
            throw ValidationException::withMessages(['published_post_ids' => 'Provide 1–20 unique post IDs.']);
        }

        return DB::transaction(function () use ($user, $org, $brand, $ids) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($user, $org, $locked, 'approval_request.update');
            $posts = PublishedPost::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
                ->whereIn('provider', array_merge(['youtube'],
                    config('planner.x_metric_sync_enabled', false) ? ['x'] : [],
                    config('planner.facebook_metric_sync_enabled', false) ? ['facebook'] : [],
                    config('planner.tiktok_metric_sync_enabled', false) ? ['tiktok'] : [],
                    config('planner.instagram_metric_sync_enabled', false) ? ['instagram'] : []))
                ->where('remote_status', 'PUBLISHED')
                ->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($posts->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'published_post_ids' => 'Only confirmed provider publications enabled for this brand are eligible.']);
            }
            $day = now('UTC')->toDateString();
            $result = [];
            foreach ($ids as $id) {
                $run = $this->repo->queue($org, $locked, $posts[$id], $user, $day);
                $result[] = ['post_id' => $id, 'sync_id' => $run->id, 'state' => $run->state];
            }
            $this->audit->record($org, $user, 'social_metrics.provider_sync_requested', 'brand', (string) $brand->id,
                $locked, ['providers' => ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], 'post_count' => count($result), 'day' => $day]);

            return ['queued' => $result, 'providers' => ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], 'day' => $day];
        }, 3);
    }
}
