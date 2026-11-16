<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum IdempotencyRecordState: string
{
    use EnumOperation;

    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
