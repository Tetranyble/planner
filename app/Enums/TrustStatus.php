<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum TrustStatus: string
{
    use EnumOperation;
    case PENDING = 'PENDING';
    case TRUSTED = 'TRUSTED';
    case REJECTED = 'REJECTED';
}
