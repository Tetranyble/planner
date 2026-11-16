<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ContentKind: string
{
    use EnumOperation;

    case TEXT = 'TEXT';
    case IMAGE = 'IMAGE';
    case CAROUSEL = 'CAROUSEL';
    case VIDEO = 'VIDEO';
    case SHORT = 'SHORT';
    case ARTICLE = 'ARTICLE';
}
