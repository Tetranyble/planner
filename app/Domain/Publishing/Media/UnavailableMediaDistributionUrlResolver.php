<?php

namespace App\Domain\Publishing\Media;

use App\Models\MediaAsset;
use App\Models\Organisation;

/** A deliberate production blocker until the installed Storage package's API is verified. */
final class UnavailableMediaDistributionUrlResolver implements MediaDistributionUrlResolverContract
{
    public function resolve(Organisation $organisation, MediaAsset $asset): string
    {
        throw new \DomainException('Tetranyble Storage delivery URL contract has not been verified.');
    }
}
