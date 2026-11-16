<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum VoiceRuleKind: string
{
    use EnumOperation;

    case DO = 'DO';
    case AVOID = 'AVOID';
    case REQUIRE = 'REQUIRE';
}
