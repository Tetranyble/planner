<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class UnipotenceKeyRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An idempotency key is required in the Idempotency-Key header or the reference/idempotency_key request field.');
    }
}
