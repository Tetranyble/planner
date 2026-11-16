<?php

namespace App\Domain\Publishing\YouTube;

use App\Models\MediaAsset;
use App\Models\Organisation;

/** Implement ONLY through reviewed Tetranyble Storage APIs, never arbitrary paths or user URLs. */
interface CanonicalVideoChunkSourceContract
{
    /** Return exactly $length bytes from the tenant's canonical object at $offset. */
    public function read(Organisation $organisation, MediaAsset $asset, int $offset, int $length): string;
}
