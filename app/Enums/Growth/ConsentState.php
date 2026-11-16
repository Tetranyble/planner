<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum ConsentState: string
{
    use EnumOperation;

    case GRANTED = 'GRANTED';
    case WITHDRAWN = 'WITHDRAWN';
    case OBJECTED = 'OBJECTED';
}
