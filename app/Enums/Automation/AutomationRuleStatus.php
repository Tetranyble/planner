<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AutomationRuleStatus: string
{
    use EnumOperation;

    case DRAFT = 'DRAFT';
    case ENABLED = 'ENABLED';
    case PAUSED = 'PAUSED';
    case RETIRED = 'RETIRED';
}
