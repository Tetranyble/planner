<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class StaleClaim extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The unipotence claim is no longer owned by this request.');
    }
}
