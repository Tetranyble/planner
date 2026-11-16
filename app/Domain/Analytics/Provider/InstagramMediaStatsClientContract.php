<?php

namespace App\Domain\Analytics\Provider;

/** Instagram professional account media insights (Facebook Login). GET-only. */
interface InstagramMediaStatsClientContract
{
    /** @return array{status:int|null,payload:array,retry_after:int|null} */
    public function read(string $accessToken, string $mediaId, string $ownerId): array;
}
