<?php

namespace App\Services\Analytics;

use App\Authorization\BrandPermissionGuard;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\BrandOperationsMetricsRepositoryContract;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** First-party workflow counters; NOT platform engagement or campaign ROI. */
final class BrandOperationsDashboard
{
    public function __construct(private readonly BrandPermissionGuard $guard,
        private readonly BrandOperationsMetricsRepositoryContract $repository) {}

    public function get(User $actor, Organisation $organisation, Brand $brand, string $from, string $to): array
    {
        if ((int) $brand->organisation_id !== (int) $organisation->id ||
            ! $this->guard->allows($actor, $brand, 'approval_request.index')) {
            throw new AuthorizationException('Brand analytics access denied.');
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');
        } catch (\Throwable) {
            throw ValidationException::withMessages(['dates' => 'Invalid UTC date range.']);
        }
        if (! $start || ! $end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to ||
            $end->lt($start) || $start->diffInDays($end) > 90) {
            throw ValidationException::withMessages(['dates' => 'Select an inclusive UTC range of no more than 90 days.']);
        }

        return [
            'source' => 'planner_internal_database', 'timezone' => 'UTC',
            'from' => $from, 'to' => $to,
            'provider_engagement_available' => false,
            'provider_engagement_note' => 'No verified provider metrics have been ingested. These are internal workflow counts only.',
            'counts' => $this->repository->aggregate($organisation, $brand,
                $start->format('Y-m-d H:i:s'), $end->addDay()->format('Y-m-d H:i:s')),
        ];
    }
}
