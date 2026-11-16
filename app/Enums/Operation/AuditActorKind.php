<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum AuditActorKind: string
{
    use EnumOperation;

    case USER = 'USER';
    case SERVICE = 'SERVICE';
    case AGENT = 'AGENT';
    case PROVIDER = 'PROVIDER';
    case WORKER = 'WORKER';
}
