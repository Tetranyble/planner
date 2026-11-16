<?php

namespace App\Domain\Analytics\Provider;

/** TikTok /video/query uses POST for a read-only ownership/metrics lookup. */
interface TikTokVideoStatsClientContract
{
    /** @return array{status:int|null,payload:array,retry_after:int|null} */
    public function read(string $accessToken, string $videoId): array;
}
