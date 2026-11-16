<?php

namespace App\Enums\Analytic;

use App\Concerns\EnumOperation;

enum AnalyticsPeriodGrain: string
{
    use EnumOperation;

    case DAY = 'DAY';
    case WEEK = 'WEEK';
    case MONTH = 'MONTH';
}
