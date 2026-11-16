<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum AudienceKind: string
{
    use EnumOperation;
    case BUSINESS = 'BUSINESS';
    case CONSUMER = 'CONSUMER';
    case PARTNER = 'PARTNER';
    case INTERNAL = 'INTERNAL';
}
