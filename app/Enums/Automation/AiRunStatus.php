<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AiRunStatus: string
{
    use EnumOperation;

    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case WAITING_APPROVAL = 'WAITING_APPROVAL';
    case WAITING_INPUT = 'WAITING_INPUT';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
