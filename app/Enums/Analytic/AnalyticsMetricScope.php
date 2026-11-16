<?php

namespace App\Enums\Analytic;

use App\Concerns\EnumOperation;

enum AnalyticsMetricScope: string
{
    use EnumOperation;

    case ACCOUNT = 'ACCOUNT';
    case POST = 'POST';
}
