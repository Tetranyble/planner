<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum StatusEnum: string
{
    use EnumOperation;
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CLOSED = 'CLOSED';
}
