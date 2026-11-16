<?php

namespace Modules\Unipotence\Domain\Exceptions;

use RuntimeException;

final class OutcomeUnknown extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The original request outcome is unknown. Reconcile it by transaction reference before retrying.');
    }
}
