<?php

namespace App\Repository\Contracts;

use App\Models\Brand;
use App\Models\Organisation;

interface SocialPublishCapabilityRepositoryContract
{
    /** Contains a short-lived decrypted credential for a service boundary ONLY. Never serialize/log. */
    public function context(Organisation $organisation, Brand $brand, string $accountUuid): array;

    /** Must reject stale OAuth token versions or moved/disconnected accounts. */
    public function record(Organisation $organisation, Brand $brand, int $accountId,
        string $credentialDigest, string $mode, string $reason): array;
}
