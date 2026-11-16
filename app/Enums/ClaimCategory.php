<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ClaimCategory: string
{
    use EnumOperation;

    case PRODUCT_CAPABILITY = 'PRODUCT_CAPABILITY';
    case QUANTIFIED = 'QUANTIFIED';
    case COMPARATIVE = 'COMPARATIVE';
    case OUTCOME = 'OUTCOME';
    case PRICING = 'PRICING';
    case OTHER = 'OTHER';
}
