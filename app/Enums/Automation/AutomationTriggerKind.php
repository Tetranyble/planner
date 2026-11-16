<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AutomationTriggerKind: string
{
    use EnumOperation;

    case SCHEDULE = 'SCHEDULE';
    case CONVERSION = 'CONVERSION';
    case WEBHOOK = 'WEBHOOK';
    case MANUAL = 'MANUAL';
}
