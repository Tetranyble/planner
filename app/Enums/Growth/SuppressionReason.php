<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum SuppressionReason: string
{
    use EnumOperation;

    case OPT_OUT = 'OPT_OUT';
    case HARD_BOUNCE = 'HARD_BOUNCE';
    case COMPLAINT = 'COMPLAINT';
    case REGULATORY = 'REGULATORY';
    case MANUAL = 'MANUAL';
}
