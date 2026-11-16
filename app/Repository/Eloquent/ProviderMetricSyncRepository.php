<?php

namespace App\Repository\Eloquent;

use App\Domain\Analytics\ProviderPerformanceSignals;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderMetricObservation;
use App\Models\ProviderMetricSyncRun;
use App\Models\PublishedPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Scoped persistence boundary. Mutating only our own local snapshots; provider GET is outside transactions. */
final class ProviderMetricSyncRepository implements ProviderMetricSyncRepositoryContract
{
    public function queue(Organisation $org, Brand $brand, PublishedPost $post, User $user, string $day): ProviderMetricSyncRun
    {
        if ((int) $post->organisation_id !== (int) $org->id
            || (int) $post->brand_id !== (int) $brand->id
            || ! in_array($post->provider, ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], true) || $post->remote_status !== 'PUBLISHED') {
            throw new \DomainException('Provider sync requires a confirmed, tenant-owned supported post.');
        }
        if ($post->provider === 'instagram' && ! config('planner.instagram_metric_sync_enabled', false)) {
            throw new \DomainException('Instagram metric API sync is disabled for this deployment.');
        }
        if ($post->provider === 'tiktok' && ! config('planner.tiktok_metric_sync_enabled', false)) {
            throw new \DomainException('TikTok metric API sync is disabled for this deployment.');
        }
        if ($post->provider === 'facebook' && ! config('planner.facebook_metric_sync_enabled', false)) {
            throw new \DomainException('Facebook metric API sync is disabled for this deployment.');
        }
        if ($post->provider === 'x' && ! config('planner.x_metric_sync_enabled', false)) {
            throw new \DomainException('X metric API sync is disabled for this deployment.');
        }
        $existing = ProviderMetricSyncRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('published_post_id', $post->id)->where('sync_day', $day)->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }
        $row = (new ProviderMetricSyncRun)->forceFill([
            'uuid' => (string) Str::uuid(), 'organisation_id' => $org->id, 'brand_id' => $brand->id,
            'published_post_id' => $post->id, 'requested_by_user_id' => $user->id,
            'provider' => $post->provider, 'sync_day' => $day, 'state' => 'PENDING', 'attempts' => 0,
            'next_attempt_at' => now(),
        ]);
        $row->save();

        return $row;
    }

    public function list(Organisation $org, Brand $brand, int $limit): Collection
    {
        return ProviderMetricSyncRun::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->orderByDesc('id')->limit(max(1, min(100, $limit)))->get();
    }

    public function recentPosts(Organisation $org, Brand $brand, int $limit): Collection
    {
        return PublishedPost::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->whereIn('provider', array_merge(['youtube'],
                config('planner.x_metric_sync_enabled', false) ? ['x'] : [],
                config('planner.facebook_metric_sync_enabled', false) ? ['facebook'] : [],
                config('planner.tiktok_metric_sync_enabled', false) ? ['tiktok'] : [],
                config('planner.instagram_metric_sync_enabled', false) ? ['instagram'] : []))
            ->where('remote_status', 'PUBLISHED')
            ->where('published_at', '>=', now('UTC')->subDays(90))
            ->orderByDesc('id')->limit(max(1, min(50, $limit)))->get(['id', 'provider', 'external_post_id', 'social_account_id', 'published_at']);
    }

    public function dueIds(int $limit): array
    {
        return ProviderMetricSyncRun::query()->whereIn('state', ['PENDING', 'RETRY_WAIT'])
            ->where('next_attempt_at', '<=', now())
            ->whereIn('provider', array_merge(['youtube'],
                config('planner.x_metric_sync_enabled', false) ? ['x'] : [],
                config('planner.facebook_metric_sync_enabled', false) ? ['facebook'] : [],
                config('planner.tiktok_metric_sync_enabled', false) ? ['tiktok'] : [],
                config('planner.instagram_metric_sync_enabled', false) ? ['instagram'] : []))
            ->orderBy('id')->limit(max(1, min(50, $limit)))
            ->pluck('id')->all();
    }

    public function claim(int $id, string $owner): ?ProviderMetricSyncRun
    {
        return DB::transaction(function () use ($id, $owner) {
            $row = ProviderMetricSyncRun::query()->whereKey($id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->state, ['PENDING', 'RETRY_WAIT'], true)
                || ! $row->next_attempt_at || $row->next_attempt_at->isFuture() || $row->attempts >= 3) {
                return null;
            }
            $row->forceFill(['state' => 'LEASED', 'lease_owner' => $owner,
                'lease_until' => now()->addMinutes(2), 'attempts' => (int) $row->attempts + 1])->save();

            return $row->fresh();
        }, 3);
    }

    public function finish(int $id, string $owner, string $code, ?array $observation = null, ?int $retryAfter = null): bool
    {
        return DB::transaction(function () use ($id, $owner, $code, $observation, $retryAfter) {
            $row = ProviderMetricSyncRun::query()->whereKey($id)->lockForUpdate()->first();
            if (! $row || $row->state !== 'LEASED' || ! hash_equals((string) $row->lease_owner, $owner)
                || ! $row->lease_until || $row->lease_until->isPast()) {
                return false;
            }
            if ($observation !== null) {
                if (! in_array($row->provider, ['youtube', 'x', 'facebook', 'tiktok', 'instagram'], true) || $code !== 'PROVIDER_API_OBSERVED'
                    || ProviderMetricObservation::query()->where('provider_metric_sync_run_id', $row->id)->exists()) {
                    return false;
                }
                $post = PublishedPost::query()->where('organisation_id', $row->organisation_id)
                    ->where('brand_id', $row->brand_id)->whereKey($row->published_post_id)->first();
                $postRef = in_array($row->provider, ['youtube', 'tiktok'], true) ? 'video_id' : 'post_id';
                $ownerRef = $row->provider === 'youtube' ? 'channel_id' : 'account_id';
                if (! $post || $post->provider !== $row->provider || $post->remote_status !== 'PUBLISHED'
                    || ! hash_equals((string) $post->external_post_id, (string) ($observation[$postRef] ?? ''))) {
                    return false;
                }
                $account = SocialAccount::query()->where('organisation_id', $row->organisation_id)
                    ->where('brand_id', $row->brand_id)->where('provider', $row->provider)
                    ->where('status', 'ACTIVE')->whereKey($post->social_account_id)->first();
                if (! $account || ! hash_equals((string) $account->external_account_id,
                    (string) ($observation[$ownerRef] ?? ''))) {
                    return false;
                }
                $expectedHash = hash('sha256', json_encode([
                    $postRef => $observation[$postRef], $ownerRef => $observation[$ownerRef],
                    'views' => $observation['views'], 'likes' => $observation['likes'],
                    'comments' => $observation['comments'],
                ], JSON_THROW_ON_ERROR));
                if (! hash_equals($expectedHash, (string) ($observation['sha256'] ?? ''))) {
                    return false;
                }
                (new ProviderMetricObservation)->forceFill([
                    'organisation_id' => $row->organisation_id, 'brand_id' => $row->brand_id,
                    'published_post_id' => $post->id, 'provider_metric_sync_run_id' => $row->id,
                    'provider' => $row->provider, 'external_post_id' => $post->external_post_id,
                    'channel_reference' => $observation[$ownerRef],
                    'views' => $observation['views'], 'likes' => $observation['likes'],
                    'comments' => $observation['comments'], 'response_sha256' => $observation['sha256'],
                    'provenance' => 'PROVIDER_API_OBSERVED', 'observed_at' => now(),
                ])->save();
                $state = 'SUCCEEDED';
            } elseif ($retryAfter !== null && $row->attempts < 3) {
                $state = 'RETRY_WAIT';
            } else {
                $state = 'FAILED';
            }
            $row->forceFill(['state' => $state, 'outcome_code' => $code,
                'lease_owner' => null, 'lease_until' => null,
                'next_attempt_at' => $state === 'RETRY_WAIT' ? now()->addSeconds(max(60, min(3600, $retryAfter))) : null,
                'completed_at' => $state === 'RETRY_WAIT' ? null : now()])->save();

            return true;
        }, 3);
    }

    public function recover(int $limit): int
    {
        $n = 0;
        foreach (ProviderMetricSyncRun::query()->where('state', 'LEASED')
            ->where('lease_until', '<', now())->orderBy('id')->limit(max(1, min(100, $limit)))
            ->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$n) {
                $row = ProviderMetricSyncRun::query()->whereKey($id)->lockForUpdate()->first();
                if (! $row || $row->state !== 'LEASED' || ! $row->lease_until || ! $row->lease_until->isPast()) {
                    return;
                }
                // GET-only probe is safe to retry; a publication POST is never handled here.
                $retry = $row->attempts < 3;
                $row->forceFill(['state' => $retry ? 'RETRY_WAIT' : 'FAILED', 'lease_owner' => null,
                    'lease_until' => null, 'next_attempt_at' => $retry ? now()->addMinutes(5) : null,
                    'outcome_code' => 'METRIC_READ_LEASE_EXPIRED', 'completed_at' => $retry ? null : now()])->save();
                $n++;
            }, 3);
        }

        return $n;
    }

    public function matchedTrend(Organisation $org, Brand $brand): array
    {
        $cutoff = now('UTC')->subDays(14);
        $earliest = now('UTC')->subDays(60);
        // Explicit bounded scan; never display partial totals or optimize from truncated evidence.
        $rows = ProviderMetricObservation::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('provider', 'youtube')
            ->where('observed_at', '>=', $earliest)->orderByDesc('observed_at')
            ->orderByDesc('id')->limit(1001)->get();
        $truncated = $rows->count() > 1000;
        $grouped = [];
        foreach ($rows->take(1000) as $row) {
            $key = (int) $row->published_post_id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = ['recent' => null, 'baseline' => null];
            }
            if ($row->observed_at->greaterThan($cutoff) && $grouped[$key]['recent'] === null) {
                $grouped[$key]['recent'] = $row;
            }
            if ($row->observed_at->lessThanOrEqualTo($cutoff) && $grouped[$key]['baseline'] === null) {
                $grouped[$key]['baseline'] = $row;
            }
        }
        $pairs = [];
        foreach ($grouped as $group) {
            if ($group['recent']?->views !== null && $group['baseline']?->views !== null) {
                $pairs[] = [(int) $group['baseline']->views, (int) $group['recent']->views];
            }
        }

        return ProviderPerformanceSignals::evaluate($pairs, $truncated);
    }

    /** Independent per-provider matched-snapshot evidence; never mix dissimilar view metrics. */
    public function matchedTrendsByProvider(Organisation $org, Brand $brand): array
    {
        $earliest = now('UTC')->subDays(60);
        $cutoff = now('UTC')->subDays(14);
        $results = [];
        foreach (['youtube', 'x', 'facebook', 'tiktok', 'instagram'] as $provider) {
            $rows = ProviderMetricObservation::query()->where('organisation_id', $org->id)
                ->where('brand_id', $brand->id)->where('provider', $provider)
                ->where('observed_at', '>=', $earliest)->orderByDesc('observed_at')
                ->orderByDesc('id')->limit(1001)->get();
            $truncated = $rows->count() > 1000;
            $byPost = [];
            foreach ($rows->take(1000) as $row) {
                $id = (int) $row->published_post_id;
                if (! isset($byPost[$id])) {
                    $byPost[$id] = ['before' => null, 'after' => null];
                }
                if ($row->observed_at->gt($cutoff) && $byPost[$id]['after'] === null) {
                    $byPost[$id]['after'] = $row;
                }
                if ($row->observed_at->lte($cutoff) && $byPost[$id]['before'] === null) {
                    $byPost[$id]['before'] = $row;
                }
            }
            $pairs = [];
            foreach ($byPost as $values) {
                if ($values['after']?->views !== null && $values['before']?->views !== null) {
                    $pairs[] = [(int) $values['before']->views, (int) $values['after']->views];
                }
            }
            $results[$provider] = ProviderPerformanceSignals::evaluate($pairs, $truncated, $provider);
            $results[$provider]['metric_definition'] = match ($provider) {
                'youtube' => 'YouTube video views','x' => 'X public post impressions',
                'facebook' => 'Facebook Page post media views', 'tiktok' => 'TikTok creator video views', 'instagram' => 'Instagram media views',
            };
        }

        return $results;
    }

    public function report(Organisation $org, Brand $brand, string $from, string $until): array
    {
        $rows = ProviderMetricObservation::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('observed_at', '>=', $from)->where('observed_at', '<', $until)
            ->orderByDesc('observed_at')->orderByDesc('id')->limit(501)->get();
        $truncated = $rows->count() > 500;
        $latest = [];
        foreach ($rows->take(500) as $row) {
            if (! isset($latest[$row->published_post_id])) {
                $latest[$row->published_post_id] = $row;
            }
        }
        $breakdown = [];
        foreach (['youtube', 'x', 'facebook', 'tiktok', 'instagram'] as $provider) {
            $providerRows = array_values(array_filter($latest, fn ($row) => $row->provider === $provider));
            $totals = null;
            if ($providerRows) {
                $totals = ['views' => 0, 'likes' => 0, 'comments' => 0];
                foreach ($totals as $field => $ignore) {
                    if (count(array_filter($providerRows, fn ($row) => $row->{$field} !== null)) !== count($providerRows)) {
                        $totals[$field] = null;

                        continue;
                    }
                    $totals[$field] = array_sum(array_map(fn ($row) => (int) $row->{$field}, $providerRows));
                }
            }
            $breakdown[$provider] = ['unique_posts' => count($providerRows), 'totals' => $truncated ? null : $totals,
                'metric_definition' => match ($provider) {
                    'youtube' => 'YouTube video views', 'x' => 'Public X post impressions when supplied',
                    'facebook' => 'Facebook Page post media views (not unique reach)',
                    'tiktok' => 'TikTok creator video views (not unique reach)',
                    'instagram' => 'Instagram media views (not unique reach)',
                }];
        }
        $populated = array_keys(array_filter($breakdown, fn ($p) => $p['unique_posts'] > 0));
        $single = count($populated) === 1 ? $populated[0] : null;

        return ['source' => 'provider_api', 'verification' => count($latest) ? 'PROVIDER_API_OBSERVED' : 'NO_PROVIDER_API_OBSERVATIONS',
            'scope' => 'PUBLISHED_POSTS_ONLY', 'truncated' => $truncated, 'aggregates_complete' => ! $truncated,
            'unique_posts_in_sample' => count($latest),
            // Never combine YouTube views and X impressions in one total.
            'totals' => ! $truncated && $single !== null ? $breakdown[$single]['totals'] : null,
            'single_provider' => $single, 'provider_breakdown' => $breakdown,
            'not_available' => ['unique_reach', 'shares', 'clicks', 'revenue', 'roi'],
            'observations' => $rows->take(30)->map(fn ($r) => [
                'id' => $r->id, 'post_id' => $r->published_post_id, 'external_post_id' => $r->external_post_id,
                'provider' => $r->provider, 'observed_at' => $r->observed_at->toIso8601String(),
                'views' => $r->views, 'likes' => $r->likes, 'comments' => $r->comments,
                'provenance' => $r->provenance,
            ])->all()];
    }
}
