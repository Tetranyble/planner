<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\User;
use App\Models\YouTubeResumableTransfer;
use Illuminate\Support\Collection;

interface YouTubeTransferRepositoryContract
{
    public function list(Organisation $org, Brand $brand, int $limit): Collection;

    public function create(Organisation $org, Brand $brand, MediaPublicationRun $run, MediaAsset $asset, User $actor): YouTubeResumableTransfer;

    public function find(Organisation $org, Brand $brand, int $id): YouTubeResumableTransfer;

    public function arm(YouTubeResumableTransfer $row, User $actor, int $version): YouTubeResumableTransfer;

    public function dueIds(int $limit): array;

    public function claim(int $id, string $owner): ?YouTubeResumableTransfer;

    public function start(int $id, string $owner): ?array;

    public function finish(int $id, string $owner, int $attemptId, array $outcome): bool;

    public function hold(int $id, string $owner, string $code): bool;

    public function recover(int $limit): array;
}
