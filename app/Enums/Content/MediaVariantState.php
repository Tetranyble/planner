<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum MediaVariantState: string
{
    use EnumOperation;

    case PROCESSING = 'PROCESSING';
    case READY = 'READY';
    case FAILED = 'FAILED';
}
