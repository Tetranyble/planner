<?php

namespace App\Services\Analytics;

use App\Authorization\BrandPermissionGuard;
use App\Authorization\CurrentOrganisation;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderMetricSyncPolicy;
use App\Models\User;
use App\Repository\Contracts\AuditEventRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncPolicyRepositoryContract;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Opt-in daily fetch of provider metrics; never requests an upload or changes published posts. */
final class ManageProviderMetricSyncSchedules
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly CurrentOrganisation $context,
        private readonly ProviderMetricSyncPolicyRepositoryContract $policies,
        private readonly ProviderMetricSyncRepositoryContract $runs,
        private readonly AuditEventRepositoryContract $audit) {}

    private function authorize(User $user, Organisation $org, Brand $brand, string $permission): void
    {
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! $this->guard->allows($user, $brand, $permission)) {
            throw new AuthorizationException('Metric sync schedule permission denied.');
        }
    }

    private function present(?ProviderMetricSyncPolicy $policy): array
    {
        if (! $policy) {
            return ['enabled' => false, 'utc_hour' => 8, 'max_posts_per_day' => 10,
                'version' => 0, 'last_queued_day' => null];
        }

        return ['enabled' => $policy->enabled, 'utc_hour' => $policy->utc_hour,
            'max_posts_per_day' => $policy->max_posts_per_day, 'version' => $policy->version,
            'last_queued_day' => $policy->last_queued_day?->format('Y-m-d')];
    }

    public function show(User $user, Organisation $org, Brand $brand): array
    {
        $this->authorize($user, $org, $brand, 'approval_request.index');

        return ['global_enabled' => (bool) config('planner.provider_metric_sync_enabled', false),
            'autoqueue_enabled' => (bool) config('planner.provider_metric_autoqueue_enabled', false),
            'schedule' => $this->present($this->policies->get($org, $brand))];
    }

    public function update(User $user, Organisation $org, Brand $brand, array $input): array
    {
        $this->authorize($user, $org, $brand, 'approval_request.update');

        return DB::transaction(function () use ($user, $org, $brand, $input) {
            Organisation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            $locked = Brand::query()->where('organisation_id', $org->id)->whereKey($brand->id)
                ->lockForUpdate()->firstOrFail();
            $this->authorize($user, $org, $locked, 'approval_request.update');
            $row = $this->policies->upsert($org, $locked, $user, (int) $input['utc_hour'],
                (int) $input['max_posts_per_day'], (bool) $input['enabled'], (int) $input['version']);
            $this->audit->record($org, $user, 'social_metrics.schedule_updated', 'brand', (string) $brand->id,
                $locked, ['enabled' => $row->enabled, 'utc_hour' => $row->utc_hour,
                    'max_posts_per_day' => $row->max_posts_per_day, 'version' => $row->version]);

            return $this->present($row);
        }, 3);
    }

    public function queueDue(int $limit = 20): array
    {
        $counts = ['disabled' => ! config('planner.provider_metric_sync_enabled', false)
            || ! config('planner.provider_metric_autoqueue_enabled', false),
            'policies' => 0, 'queued' => 0, 'blocked' => 0];
        if ($counts['disabled']) {
            return $counts;
        }
        foreach ($this->policies->dueIds((int) now('UTC')->format('G'), $limit) as $id) {
            try {
                $created = DB::transaction(function () use ($id) {
                    $candidate = ProviderMetricSyncPolicy::query()->whereKey((int) $id)->first();
                    if (! $candidate) {
                        return null;
                    }
                    // Consistent lock order: organisation -> brand -> policy, matching operator updates.
                    $org = Organisation::query()->whereKey($candidate->organisation_id)->lockForUpdate()->first();
                    $brand = Brand::query()->where('organisation_id', $candidate->organisation_id)
                        ->whereKey($candidate->brand_id)->lockForUpdate()->first();
                    $row = $this->policies->lockById((int) $id);
                    if (! $row || ! $row->enabled || (int) $row->utc_hour !== (int) now('UTC')->format('G')
                        || ($row->last_queued_day && $row->last_queued_day->toDateString() >= now('UTC')->toDateString())) {
                        return null;
                    }
                    $actor = User::query()->whereKey($row->configured_by_user_id)->first();
                    if (! $org || ! $brand || ! $actor) {
                        throw new ConflictHttpException('Scheduling actor invalid.');
                    }

                    return $this->context->within($actor, (int) $org->id, function () use ($row, $org, $brand, $actor) {
                        $this->authorize($actor, $org, $brand, 'approval_request.update');
                        $day = now('UTC')->toDateString();
                        $count = 0;
                        foreach ($this->runs->recentPosts($org, $brand, (int) $row->max_posts_per_day) as $post) {
                            $this->runs->queue($org, $brand, $post, $actor, $day);
                            $count++;
                        }
                        $row->forceFill(['last_queued_day' => $day])->save();

                        return $count;
                    });
                }, 3);
                if ($created !== null) {
                    $counts['policies']++;
                    $counts['queued'] += $created;
                }
            } catch (\Throwable) {
                // Do not log actor details or provider data. Failed policies can be retried.
                $counts['blocked']++;
            }
        }

        return $counts;
    }
}
