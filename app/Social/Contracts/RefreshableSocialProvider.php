<?php

namespace App\Social\Contracts;

use App\Social\Data\TokenRefreshResult;

/** Optional capability. Access-only providers require consent again, not a synthetic refresh. */
interface RefreshableSocialProvider
{
    /** This call performs provider I/O. Never hold a DB transaction while invoking it. */
    public function refresh(string $refreshToken): TokenRefreshResult;
}
