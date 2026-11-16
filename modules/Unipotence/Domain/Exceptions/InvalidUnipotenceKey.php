<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class InvalidUnipotenceKey extends RuntimeException
{
    public function __construct(int $maxLength)
    {
        parent::__construct(
            "The idempotency key must contain 1-{$maxLength} letters, numbers, periods, underscores, colons, or hyphens.",
        );
    }
}
