<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum StateEnum: string
{
    use EnumOperation;
    // 'invited','active','suspended','revoked'
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case REVOKED = 'REVOKED';
    case INVITED = 'INVITED';
}
