<?php

namespace App\Domain\Publishing\YouTube;

use App\Models\MediaAsset;
use App\Models\Organisation;

interface YouTubeUploadTransportContract
{
    /** Sanitized outcomes ONLY: never persist raw provider bodies, credentials or session URLs in audit events. */
    public function execute(string $action, string $token, ?string $sessionUrl, ?string $videoId,
        Organisation $organisation, MediaAsset $asset, int $offset, string $caption, string $channelId): array;
}
