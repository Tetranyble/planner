<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum SourceType: string
{
    use EnumOperation;
    case WEBSITE = 'WEBSITE';
    case DOCUMENT = 'DOCUMENT';
    case MANUAL = 'MANUAL';
    case API = 'API';
    case SUPPORT_ARTICLE = 'SUPPORT_ARTICLE';
}
