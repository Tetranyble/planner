<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AiToolExecutionStatus: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case RUNNING = 'RUNNING';
    case WAITING_APPROVAL = 'WAITING_APPROVAL';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case UNCERTAIN = 'UNCERTAIN';
}
