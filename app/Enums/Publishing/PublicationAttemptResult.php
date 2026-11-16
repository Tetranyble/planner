<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum PublicationAttemptResult: string
{
    use EnumOperation;

    case STARTED = 'STARTED';
    case SUCCESS = 'SUCCESS';
    case RETRYABLE = 'RETRYABLE';
    case FAILED = 'FAILED';
    case UNCERTAIN = 'UNCERTAIN';
}
