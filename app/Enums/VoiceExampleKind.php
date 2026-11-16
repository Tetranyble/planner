<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum VoiceExampleKind: string
{
    use EnumOperation;

    case GOOD = 'GOOD';
    case BAD = 'BAD';
}
