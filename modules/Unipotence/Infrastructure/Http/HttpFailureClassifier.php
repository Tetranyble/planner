<?php

namespace Modules\Unipotence\Infrastructure\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Modules\Unipotence\Domain\Contracts\DefinitelyNotDispatched;
use Modules\Unipotence\Domain\Contracts\FailureClassifier;
use Throwable;

final class HttpFailureClassifier implements FailureClassifier
{
    public function isSafeToRetry(Throwable $exception): bool
    {
        return $exception instanceof DefinitelyNotDispatched
            || $exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException;
    }
}
