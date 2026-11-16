<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum LeadContactStatus: string
{
    use EnumOperation;

    case ACTIVE = 'ACTIVE';
    case SUPPRESSED = 'SUPPRESSED';
    case ERASED = 'ERASED';
}
