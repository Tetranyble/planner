<?php

namespace App\Domain\Analytics\Provider;

/** Strictly read-only provider gateway. The worker owns credential eligibility. */
interface YouTubeVideoStatsClientContract
{
    /** @return array{status:int|null,payload:array,retry_after:int|null} */
    public function read(string $accessToken, string $videoId): array;
}
