<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AiActionApprovalStatus: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
}
