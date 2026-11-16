<?php

namespace App\Enums\Analytic;

use App\Concerns\EnumOperation;

enum AnalyticsCollectionStatus: string
{
    use EnumOperation;

    case COMPLETE = 'COMPLETE';
    case PARTIAL = 'PARTIAL';
}
