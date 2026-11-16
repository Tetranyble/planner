<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\MediaPublicationPreparation;
use App\Models\MediaPublicationRun;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Collection;

interface MediaPublicationRunRepositoryContract
{
    public function list(Organisation $org, Brand $brand, int $limit): Collection;

    public function find(Organisation $org, Brand $brand, int $id, bool $lock = false): MediaPublicationRun;

    public function create(Organisation $org, Brand $brand, MediaPublicationPreparation $prep, User $actor): MediaPublicationRun;

    public function arm(MediaPublicationRun $run, User $actor, int $version): MediaPublicationRun;

    public function cancel(MediaPublicationRun $run, User $actor, int $version): MediaPublicationRun;

    public function dueIds(int $limit): array;

    public function claim(int $id, string $owner): ?MediaPublicationRun;

    public function holdClaim(int $id, string $owner, string $code): bool;

    public function stageIsFenced(int $id, string $owner, int $attemptId): bool;

    public function startStage(int $id, string $owner): ?array;

    public function finishStage(int $id, string $owner, int $attemptId, array $result): bool;

    /** Atomic, provider-verified completion for a YouTube resumable transfer. */
    public function completeVerifiedYouTube(int $runId, string $videoId, string $channelId): ?string;

    /** Terminally quarantine an ambiguous YouTube transfer and its publication plan. */
    public function quarantineYouTube(int $runId, string $code): void;

    public function quarantineExpired(int $limit): array;
}
