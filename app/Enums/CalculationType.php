<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum CalculationType: string
{
    use EnumOperation;
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED = 'FIXED';
}
