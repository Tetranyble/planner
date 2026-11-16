<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum TimeFormat: string
{
    use EnumOperation;

    case TIME_24_HOUR = 'H:i:s';
    case TIME_12_HOUR = 'h:i:s A';
}
