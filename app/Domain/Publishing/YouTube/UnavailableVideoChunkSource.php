<?php

namespace App\Domain\Publishing\YouTube;

use App\Models\MediaAsset;
use App\Models\Organisation;

final class UnavailableVideoChunkSource implements CanonicalVideoChunkSourceContract
{
    public function read(Organisation $organisation, MediaAsset $asset, int $offset, int $length): string
    {
        throw new \DomainException('Tetranyble Storage canonical byte reader has not been validated.');
    }
}
