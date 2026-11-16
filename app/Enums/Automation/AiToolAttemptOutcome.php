<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AiToolAttemptOutcome: string
{
    use EnumOperation;

    case STARTED = 'STARTED';
    case SUCCESS = 'SUCCESS';
    case ERROR = 'ERROR';
    case UNCERTAIN = 'UNCERTAIN';
}
