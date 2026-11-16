<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum RetentionRequestKind: string
{
    use EnumOperation;

    case DELETE = 'DELETE';
    case EXPORT = 'EXPORT';
    case RESTRICT = 'RESTRICT';
    case RECTIFY = 'RECTIFY';
}
