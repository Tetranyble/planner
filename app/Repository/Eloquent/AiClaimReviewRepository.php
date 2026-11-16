<?php

namespace App\Repository\Eloquent;

use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\Organisation;
use App\Repository\Contracts\AiClaimReviewRepositoryContract;

final class AiClaimReviewRepository implements AiClaimReviewRepositoryContract
{
    public function activeApprovedWording(Organisation $org, Brand $brand): array
    {
        return BrandClaim::query()->where('organisation_id', $org->id)->where('brand_id', $brand->id)
            ->where('status', 'APPROVED')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->limit(25)->pluck('claim_text')->all();
    }
}
