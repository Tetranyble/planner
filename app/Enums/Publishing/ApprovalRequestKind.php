<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum ApprovalRequestKind: string
{
    use EnumOperation;

    case HUMAN = 'HUMAN';
    case STANDING_POLICY = 'STANDING_POLICY';
}
