<?php

namespace App\Repository\Contracts;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\MediaPublicationPreparation;
use App\Models\Organisation;
use App\Models\PublicationJob;
use App\Models\User;
use Illuminate\Support\Collection;

interface MediaPublicationPreparationRepositoryContract
{
    public function list(Organisation $org, Brand $brand, int $limit): Collection;

    public function find(Organisation $org, Brand $brand, int $id, bool $lock = false): MediaPublicationPreparation;

    public function existing(Organisation $org, Brand $brand, PublicationJob $job): ?MediaPublicationPreparation;

    public function create(Organisation $org, Brand $brand, PublicationJob $job, MediaAsset $asset, ApprovalRequest $approval,
        User $actor, array $manifest, ?string $blocker): MediaPublicationPreparation;

    public function abort(MediaPublicationPreparation $preparation, User $actor): void;
}
