<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface AiClaimReviewRepositoryContract
{
    /** Editorially approved wording only; does not claim independent factual verification. */
    public function activeApprovedWording(Organisation $org, Brand $brand): array;
}
