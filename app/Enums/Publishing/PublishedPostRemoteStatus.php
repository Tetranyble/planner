<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum PublishedPostRemoteStatus: string
{
    use EnumOperation;

    case PUBLISHED = 'PUBLISHED';
    case PROCESSING = 'PROCESSING';
    case REMOVED = 'REMOVED';
    case UNKNOWN = 'UNKNOWN';
}
