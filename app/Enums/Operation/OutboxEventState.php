<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum OutboxEventState: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case LEASED = 'LEASED';
    case DISPATCHED = 'DISPATCHED';
    case FAILED = 'FAILED';
}
