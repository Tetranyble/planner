<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum TrackingLinkStatus: string
{
    use EnumOperation;

    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case RETIRED = 'RETIRED';
}
