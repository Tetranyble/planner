<?php

namespace App\Domain\Publishing\Delivery;

/** Transport boundary. Implementations must NOT persist results or change job state. */
interface ProviderDispatchContract
{
    public function provider(): string;

    public function dispatch(DispatchEnvelope $envelope): DispatchOutcome;
}
