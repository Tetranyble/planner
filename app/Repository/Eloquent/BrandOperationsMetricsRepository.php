<?php

namespace App\Repository\Eloquent;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentItem;
use App\Models\ContentTarget;
use App\Models\Organisation;
use App\Models\PublicationAttempt;
use App\Models\PublicationJob;
use App\Repository\Contracts\BrandOperationsMetricsRepositoryContract;

final class BrandOperationsMetricsRepository implements BrandOperationsMetricsRepositoryContract
{
    public function aggregate(Organisation $organisation, Brand $brand, string $from, string $untilExclusive): array
    {
        $scope = static fn (string $column, string $model) => $model::query()
            ->where('organisation_id', $organisation->id)->where('brand_id', $brand->id)
            ->where($column, '>=', $from)->where($column, '<', $untilExclusive);
        $byState = static fn ($query, string $field) => $query->selectRaw($field.' AS metric_state, COUNT(*) AS metric_count')
            ->groupBy($field)->pluck('metric_count', 'metric_state')->map(fn ($n) => (int) $n)->all();

        return [
            'content' => [
                'total' => $scope('created_at', ContentItem::class)->count(),
                'by_state' => $byState($scope('created_at', ContentItem::class), 'state'),
                'by_kind' => $byState($scope('created_at', ContentItem::class), 'content_kind'),
            ],
            'targets' => ['total' => $scope('created_at', ContentTarget::class)->count()],
            'approvals' => [
                'total' => $scope('created_at', ApprovalRequest::class)->count(),
                'by_status' => $byState($scope('created_at', ApprovalRequest::class), 'status'),
            ],
            'publication_plans' => [
                'total' => $scope('created_at', PublicationJob::class)->count(),
                'by_state' => $byState($scope('created_at', PublicationJob::class), 'state'),
                'overdue_held' => PublicationJob::query()->where('organisation_id', $organisation->id)
                    ->where('brand_id', $brand->id)->where('state', 'AWAITING_USER')
                    ->where('run_at', '<', now('UTC'))->count(),
            ],
            'attempts' => ['total' => $scope('created_at', PublicationAttempt::class)->count()],
        ];
    }
}
