<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\MediaPublicationRun;
use App\Models\MediaTikTokConsent;
use App\Models\Organisation;
use App\Models\User;

interface MediaTikTokConsentRepositoryContract
{
    public function find(Organisation $org, Brand $brand, MediaPublicationRun $run): ?MediaTikTokConsent;

    public function create(Organisation $org, Brand $brand, MediaPublicationRun $run, User $actor,
        array $selection, array $verifiedOptions, string $grantFingerprint, string $approvalHash, string $mediaSha): MediaTikTokConsent;
}
