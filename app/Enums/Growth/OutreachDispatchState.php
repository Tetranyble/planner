<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum OutreachDispatchState: string
{
    use EnumOperation;

    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case SENDING = 'SENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case SUPPRESSED = 'SUPPRESSED';
}
