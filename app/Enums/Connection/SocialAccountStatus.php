<?php

namespace App\Enums\Connection;

use App\Concerns\EnumOperation;

enum SocialAccountStatus: string
{
    use EnumOperation;

    case ACTIVE = 'ACTIVE';
    case DISCONNECTED = 'DISCONNECTED';
    case RESTRICTED = 'RESTRICTED';
    case ARCHIVED = 'ARCHIVED';
}
