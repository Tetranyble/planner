<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum MediaAssetState: string
{
    use EnumOperation;

    case UPLOADED = 'UPLOADED';
    case PROCESSING = 'PROCESSING';
    case READY = 'READY';
    case REJECTED = 'REJECTED';
    case DELETED = 'DELETED';
}
