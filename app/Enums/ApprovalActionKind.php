<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ApprovalActionKind: string
{
    use EnumOperation;

    case PUBLISH = 'PUBLISH';
    case SEND_OUTREACH = 'SEND_OUTREACH';
    case EDIT_SCHEDULED = 'EDIT_SCHEDULED';
    case DELETE_PUBLISHED = 'DELETE_PUBLISHED';
}
