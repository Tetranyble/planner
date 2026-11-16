<?php

namespace App\Domain\Publishing\Delivery;

/** No URL, HTTP client, or credential capability is exposed by this contract. */
interface MockHttpTransportContract
{
    public function exchange(MockHttpExchange $request): MockHttpReply;
}
