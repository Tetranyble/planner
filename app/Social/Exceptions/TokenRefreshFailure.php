<?php

namespace App\Social\Exceptions;

use RuntimeException;

/** Sanitized failure: never attach raw provider responses or exception messages containing credentials. */
final class TokenRefreshFailure extends RuntimeException
{
    public function __construct(public readonly string $safeCode, public readonly bool $retrySafe = false)
    {
        parent::__construct('Social credential renewal requires attention.');
    }
}
