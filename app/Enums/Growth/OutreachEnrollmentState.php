<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum OutreachEnrollmentState: string
{
    use EnumOperation;

    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case PAUSED = 'PAUSED';
    case STOPPED = 'STOPPED';
}
