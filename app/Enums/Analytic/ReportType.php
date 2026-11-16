<?php

namespace App\Enums\Analytic;

use App\Concerns\EnumOperation;

enum ReportType: string
{
    use EnumOperation;

    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';
    case CAMPAIGN = 'CAMPAIGN';
    case AD_HOC = 'AD_HOC';
}
