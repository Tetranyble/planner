<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum IntegrationSyncState: string
{
    use EnumOperation;

    case READY = 'READY';
    case RUNNING = 'RUNNING';
    case ERROR = 'ERROR';
    case DISABLED = 'DISABLED';
}
