<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\ProviderMetricSyncRun;
use App\Models\PublishedPost;
use App\Models\User;
use Illuminate\Support\Collection;

interface ProviderMetricSyncRepositoryContract
{
    public function queue(Organisation $org, Brand $brand, PublishedPost $post, User $user, string $day): ProviderMetricSyncRun;

    public function list(Organisation $org, Brand $brand, int $limit): Collection;

    public function recentPosts(Organisation $org, Brand $brand, int $limit): Collection;

    public function dueIds(int $limit): array;

    public function claim(int $id, string $owner): ?ProviderMetricSyncRun;

    public function finish(int $id, string $owner, string $code, ?array $observation = null, ?int $retryAfter = null): bool;

    public function recover(int $limit): int;

    public function report(Organisation $org, Brand $brand, string $from, string $until): array;

    public function matchedTrend(Organisation $org, Brand $brand): array;

    public function matchedTrendsByProvider(Organisation $org,Brand $brand): array;
}
