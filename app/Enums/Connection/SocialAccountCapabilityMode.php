<?php

namespace App\Enums\Connection;

use App\Concerns\EnumOperation;

enum SocialAccountCapabilityMode: string
{
    use EnumOperation;

    case AUTOMATIC = 'AUTOMATIC';
    case ASSISTED = 'ASSISTED';
    case UNAVAILABLE = 'UNAVAILABLE';
    case READ_ONLY = 'READ_ONLY';
}
