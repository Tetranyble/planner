<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum WebhookEventState: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PROCESSED = 'PROCESSED';
    case FAILED = 'FAILED';
    case QUARANTINED = 'QUARANTINED';
}
