<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum DecisionRole: string
{
    use EnumOperation;

    case DECISION_MAKER = 'DECISION_MAKER';
    case BUYER = 'BUYER';
    case INFLUENCER = 'INFLUENCER';
    case END_USER = 'END_USER';
    case OTHER = 'OTHER';
}
