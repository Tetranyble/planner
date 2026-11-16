<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum ContentItemState: string
{
    use EnumOperation;

    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
