<?php

namespace App\Enums\Connection;

use App\Concerns\EnumOperation;

enum ProviderConnectionStatus: string
{
    use EnumOperation;

    case CONNECTED = 'CONNECTED';
    case REAUTH_REQUIRED = 'REAUTH_REQUIRED';
    case RESTRICTED = 'RESTRICTED';
    case REVOKED = 'REVOKED';
    case DISCONNECTED = 'DISCONNECTED';
}
