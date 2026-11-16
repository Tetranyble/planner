<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum MediaSourceType: string
{
    use EnumOperation;

    case UPLOAD = 'UPLOAD';
    case GENERATED = 'GENERATED';
    case IMPORTED = 'IMPORTED';
}
