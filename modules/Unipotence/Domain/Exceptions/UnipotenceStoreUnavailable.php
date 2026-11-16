<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class UnipotenceStoreUnavailable extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(
            'The idempotency service is temporarily unavailable. Retry the request later with the same key.',
            previous: $previous,
        );
    }
}
