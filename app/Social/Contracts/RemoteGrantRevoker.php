<?php

namespace App\Social\Contracts;

interface RemoteGrantRevoker
{
    public function provider(): string;

    /** Throws on any ambiguous or failed response. Does not log secrets. */
    public function revoke(string $accessToken): void;
}
