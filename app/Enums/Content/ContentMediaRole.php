<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum ContentMediaRole: string
{
    use EnumOperation;

    case PRIMARY = 'PRIMARY';
    case SECONDARY = 'SECONDARY';
    case THUMBNAIL = 'THUMBNAIL';
}
