<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class RequestInFlight extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A request with this idempotency key is still being processed.');
    }
}
