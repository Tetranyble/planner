<?php

namespace App\Domain\Publishing\LiveContracts;

interface TextHttpTransportContract
{
    /** Never log, cache, return, or persist the bearer token. */
    public function send(PreparedProviderRequest $request, string $bearerToken): TextHttpResponse;
}
