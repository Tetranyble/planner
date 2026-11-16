<?php

namespace App\Domain\Analytics\Provider;

interface XPostStatsClientContract
{
    /** HTTP status null on connection failure. Raw errors must never be persisted. */
    public function read(string $accessToken, string $postId): array;
}
