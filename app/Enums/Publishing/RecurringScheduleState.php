<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum RecurringScheduleState: string
{
    use EnumOperation;

    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case RETIRED = 'RETIRED';
}
