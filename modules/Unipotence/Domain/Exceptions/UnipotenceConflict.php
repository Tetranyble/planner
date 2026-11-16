<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class UnipotenceConflict extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The idempotency key was already used with a different request.');
    }
}
