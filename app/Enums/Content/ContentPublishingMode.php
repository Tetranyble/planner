<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum ContentPublishingMode: string
{
    use EnumOperation;

    case AUTOMATIC = 'AUTOMATIC';
    case ASSISTED = 'ASSISTED';
}
