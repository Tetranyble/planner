<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum OutreachSequenceStatus: string
{
    use EnumOperation;

    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case RETIRED = 'RETIRED';
}
