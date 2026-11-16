<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ApprovalPolicyState: string
{
    use EnumOperation;

    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case RETIRED = 'RETIRED';
}
