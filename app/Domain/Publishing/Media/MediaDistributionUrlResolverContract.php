<?php

namespace App\Domain\Publishing\Media;

use App\Models\MediaAsset;
use App\Models\Organisation;

/** Must use official Tetranyble Storage distribution APIs, never user-provided URLs. */
interface MediaDistributionUrlResolverContract
{
    public function resolve(Organisation $organisation, MediaAsset $asset): string;
}
