<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\ProviderMetricSyncRun;
use App\Models\PublicationJob;
use App\Repository\Contracts\PlannerOperationalSafetyRepositoryContract;

final class PlannerOperationalSafetyRepository implements PlannerOperationalSafetyRepositoryContract
{
    public function summary(Organisation $org, Brand $brand): array
    {
        $scope = static fn ($query) => $query->where('organisation_id', $org->getKey())->where('brand_id', $brand->getKey());
        $jobs = $scope(PublicationJob::query());
        $media = $scope(MediaPublicationRun::query());
        $metrics = $scope(ProviderMetricSyncRun::query());

        return [
            'publication_uncertain' => (clone $jobs)->where('state', 'UNCERTAIN')->count(),
            'publication_failed' => (clone $jobs)->where('state', 'FAILED')->count(),
            'publication_expired_leases' => (clone $jobs)->whereIn('state', ['LEASED', 'PUBLISHING'])
                ->whereNotNull('lease_until')->where('lease_until', '<', now())->count(),
            'publication_due_backlog' => (clone $jobs)->whereIn('state', ['SCHEDULED', 'RETRY_WAIT'])
                ->where('run_at', '<', now()->subMinutes(15))->count(),
            'media_uncertain' => (clone $media)->whereIn('state', ['UNCERTAIN', 'MANUAL_REQUIRED'])->count(),
            'media_expired_leases' => (clone $media)->whereNotNull('lease_owner')
                ->whereNotNull('lease_until')->where('lease_until', '<', now())->count(),
            'metrics_failed' => (clone $metrics)->where('state', 'FAILED')->count(),
            'metrics_expired_leases' => (clone $metrics)->whereNotNull('lease_owner')
                ->whereNotNull('lease_until')->where('lease_until', '<', now())->count(),
        ];
    }
}
