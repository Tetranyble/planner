<?php

namespace App\Repository\Eloquent;

use App\Models\Campaign;
use App\Repository\BrandScopedRepository;
use App\Repository\Contracts\CampaignRepositoryContract;

/** @extends BrandScopedRepository<Campaign> */
final class CampaignRepository extends BrandScopedRepository implements CampaignRepositoryContract
{
    public function __construct(Campaign $model)
    {
        parent::__construct($model);
    }
}
