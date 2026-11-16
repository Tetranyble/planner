<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum LeadStatus: string
{
    use EnumOperation;

    case NEW = 'NEW';
    case QUALIFYING = 'QUALIFYING';
    case QUALIFIED = 'QUALIFIED';
    case DEMO = 'DEMO';
    case TRIAL = 'TRIAL';
    case WON = 'WON';
    case LOST = 'LOST';
    case DISQUALIFIED = 'DISQUALIFIED';
}
