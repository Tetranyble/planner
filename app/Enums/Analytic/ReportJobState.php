<?php

namespace App\Enums\Analytic;

use App\Concerns\EnumOperation;

enum ReportJobState: string
{
    use EnumOperation;

    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
