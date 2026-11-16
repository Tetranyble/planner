<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ClaimRestrictionKind: string
{
    use EnumOperation;

    case PROHIBITED_PHRASE = 'PROHIBITED_PHRASE';
    case RESTRICTED_TOPIC = 'RESTRICTED_TOPIC';
}
