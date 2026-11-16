<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderMetricSyncPolicy;
use App\Models\User;
use App\Repository\Contracts\ProviderMetricSyncPolicyRepositoryContract;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ProviderMetricSyncPolicyRepository implements ProviderMetricSyncPolicyRepositoryContract
{
    public function get(Organisation $org, Brand $brand, bool $lock = false): ?ProviderMetricSyncPolicy
    {
        return ProviderMetricSyncPolicy::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->when($lock, fn ($q) => $q->lockForUpdate())->first();
    }

    public function upsert(Organisation $org, Brand $brand, User $user, int $hour, int $limit, bool $enabled, ?int $version): ProviderMetricSyncPolicy
    {
        $row = $this->get($org, $brand, true);
        if ($row && $version !== (int) $row->version) {
            throw new ConflictHttpException('Sync schedule was changed by another operator.');
        }
        if (! $row && $version !== null && $version !== 0) {
            throw new ConflictHttpException('New sync schedule expects version zero.');
        }
        if (! $row) {
            $row = new ProviderMetricSyncPolicy;
        }
        $row->forceFill(['organisation_id' => $org->id, 'brand_id' => $brand->id,
            'configured_by_user_id' => $user->id, 'enabled' => $enabled,
            'utc_hour' => $hour, 'max_posts_per_day' => $limit,
            'version' => $row->exists ? (int) $row->version + 1 : 1])->save();

        return $row;
    }

    public function dueIds(int $utcHour, int $limit): array
    {
        $date = now('UTC')->toDateString();

        return ProviderMetricSyncPolicy::query()->where('enabled', true)->where('utc_hour', $utcHour)
            ->where(fn ($q) => $q->whereNull('last_queued_day')->orWhere('last_queued_day', '<', $date))
            ->orderBy('id')->limit(max(1, min(50, $limit)))->pluck('id')->all();
    }

    public function lockById(int $id): ?ProviderMetricSyncPolicy
    {
        return ProviderMetricSyncPolicy::query()->whereKey($id)->lockForUpdate()->first();
    }
}
