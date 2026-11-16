<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum NotificationState: string
{
    use EnumOperation;

    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case READ = 'READ';
    case FAILED = 'FAILED';
}
