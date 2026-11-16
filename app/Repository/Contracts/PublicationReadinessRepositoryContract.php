<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialAccount;

interface PublicationReadinessRepositoryContract
{
    /** Only scoped, unexpired AUTOMATIC publish capabilities. */
    public function hasVerifiedAutomaticPublishCapability(Organisation $organisation, Brand $brand, SocialAccount $account): bool;

    public function hasVerifiedMediaPublishGrant(Organisation $organisation, Brand $brand, SocialAccount $account): bool;

    /** Never returns token material, only a conservative credential-health result. */
    public function connectionIsHealthy(Organisation $organisation, Brand $brand, SocialAccount $account): array;
}
