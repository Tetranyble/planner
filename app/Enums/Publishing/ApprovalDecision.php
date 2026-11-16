<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum ApprovalDecision: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
}
