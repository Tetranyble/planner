<?php

namespace App\Domain\Analytics\Provider;

/** GET-only Meta Page post metric probe. No credentials or raw provider HTTP bodies are stored. */
interface FacebookPagePostStatsClientContract
{
    /** @return array{status:int|null,payload:array,retry_after:int|null} */
    public function read(string $pageToken, string $postId, string $pageId): array;
}
