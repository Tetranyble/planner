<?php

namespace App\Enums\Analytic;

use App\Concerns\EnumOperation;

enum ReportFormat: string
{
    use EnumOperation;

    case CSV = 'CSV';
    case PDF = 'PDF';
    case JSON = 'JSON';
}
