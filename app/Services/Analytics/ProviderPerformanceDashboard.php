<?php

namespace App\Services\Analytics;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\ProviderMetricSyncRepositoryContract;
use Illuminate\Auth\Access\AuthorizationException;

final class ProviderPerformanceDashboard
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly ProviderMetricSyncRepositoryContract $metrics) {}

    public function show(User $actor, Organisation $org, Brand $brand): array
    {
        if ((int) $brand->organisation_id !== (int) $org->id
            || ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Provider analytics access denied.');
        }

        return ['platforms' => $this->metrics->matchedTrendsByProvider($org, $brand),
            // Kept for compatibility with clients showing YouTube-only signals.
            'youtube_legacy' => $this->metrics->matchedTrend($org, $brand),
            'cross_provider_aggregation_allowed' => false,
            'automated_content_changes_authorized' => false];
    }
}
