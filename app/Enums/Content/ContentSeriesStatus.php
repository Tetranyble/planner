<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum ContentSeriesStatus: string
{
    use EnumOperation;

    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case ARCHIVED = 'ARCHIVED';
}
