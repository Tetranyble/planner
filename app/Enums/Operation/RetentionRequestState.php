<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum RetentionRequestState: string
{
    use EnumOperation;

    case RECEIVED = 'RECEIVED';
    case VERIFIED = 'VERIFIED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case REJECTED = 'REJECTED';
}
