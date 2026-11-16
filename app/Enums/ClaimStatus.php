<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ClaimStatus: string
{
    use EnumOperation;

    case DRAFT = 'DRAFT';
    case IN_REVIEW = 'IN_REVIEW';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case RETIRED = 'RETIRED';
}
