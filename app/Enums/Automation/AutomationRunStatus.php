<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AutomationRunStatus: string
{
    use EnumOperation;

    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case WAITING_APPROVAL = 'WAITING_APPROVAL';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
