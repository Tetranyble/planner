<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum ApprovalRequestStatus: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case WITHDRAWN = 'WITHDRAWN';
    case EXPIRED = 'EXPIRED';
}
