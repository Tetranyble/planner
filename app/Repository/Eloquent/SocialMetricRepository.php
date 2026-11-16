<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialMetricSnapshot;
use App\Repository\Contracts\SocialMetricRepositoryContract;

final class SocialMetricRepository implements SocialMetricRepositoryContract
{
    public function findSubmission(Organisation $org, Brand $brand, string $key): ?SocialMetricSnapshot
    {
        return SocialMetricSnapshot::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('submission_key', $key)->lockForUpdate()->first();
    }

    public function store(Organisation $org, Brand $brand, int $userId, array $values): SocialMetricSnapshot
    {
        $row = new SocialMetricSnapshot;
        $row->forceFill($values + ['organisation_id' => $org->id, 'brand_id' => $brand->id,
            'submitted_by_user_id' => $userId])->save();

        return $row;
    }

    public function report(Organisation $org, Brand $brand, string $from, string $to): array
    {
        $base = SocialMetricSnapshot::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('observed_at', '>=', $from)->where('observed_at', '<', $to);
        $snapshots = (clone $base)->orderByDesc('observed_at')->orderByDesc('id')->limit(501)->get();
        $truncated = $snapshots->count() > 500;
        $latest = [];
        foreach ($snapshots->take(500) as $snapshot) {
            $key = $snapshot->provider."\0".$snapshot->account_reference."\0".$snapshot->external_post_reference;
            if (! isset($latest[$key])) {
                $latest[$key] = $snapshot;
            }
        }
        $fields = ['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks'];
        $totals = array_fill_keys($fields, 0);
        $platforms = [];
        foreach ($latest as $record) {
            $name = $record->provider;
            if (! isset($platforms[$name])) {
                $platforms[$name] = ['posts' => 0] + array_fill_keys($fields, 0);
            }
            $platforms[$name]['posts']++;
            foreach ($fields as $field) {
                $totals[$field] += (int) $record->{$field};
                $platforms[$name][$field] += (int) $record->{$field};
            }
        }

        return ['source' => 'manual_provider_export', 'verification' => 'USER_REPORTED_UNVERIFIED',
            'snapshots_scanned' => min(500, $snapshots->count()), 'truncated' => $truncated,
            'aggregates_complete' => ! $truncated, 'unique_posts_in_sample' => count($latest),
            'totals' => $truncated ? null : $totals, 'platforms' => $truncated ? null : $platforms,
            'snapshots' => $snapshots->take(50)->map(fn ($row) => [
                'id' => $row->id, 'provider' => $row->provider, 'account_reference' => $row->account_reference,
                'external_post_reference' => $row->external_post_reference, 'observed_at' => $row->observed_at->toIso8601String(),
                'impressions' => (int) $row->impressions, 'reach' => (int) $row->reach,
                'likes' => (int) $row->likes, 'comments' => (int) $row->comments,
                'shares' => (int) $row->shares, 'clicks' => (int) $row->clicks,
                'source_type' => $row->source_type, 'source_reference' => $row->source_reference,
            ])->values()->all()];
    }
}
