<?php

namespace Modules\Unipotence\Domain\Contracts;

use Throwable;

interface FailureClassifier
{
    public function isSafeToRetry(Throwable $exception): bool;
}
