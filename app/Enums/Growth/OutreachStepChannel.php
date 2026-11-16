<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum OutreachStepChannel: string
{
    use EnumOperation;

    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
}
