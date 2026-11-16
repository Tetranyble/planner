<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum VoiceTermUsage: string
{
    use EnumOperation;

    case PREFER = 'PREFER';
    case AVOID = 'AVOID';
    case REPLACE = 'REPLACE';
}
