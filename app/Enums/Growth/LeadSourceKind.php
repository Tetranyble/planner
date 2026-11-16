<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum LeadSourceKind: string
{
    use EnumOperation;

    case INBOUND = 'INBOUND';
    case REFERRAL = 'REFERRAL';
    case EVENT = 'EVENT';
    case ORGANIC = 'ORGANIC';
    case PAID = 'PAID';
    case OUTREACH = 'OUTREACH';
}
