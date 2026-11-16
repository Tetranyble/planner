<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum VoiceEnforcement: string
{
    use EnumOperation;

    case GUIDELINE = 'GUIDELINE';
    case MANDATORY = 'MANDATORY';
}
