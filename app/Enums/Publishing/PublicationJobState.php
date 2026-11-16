<?php

namespace App\Enums\Publishing;

use App\Concerns\EnumOperation;

enum PublicationJobState: string
{
    use EnumOperation;

    case SCHEDULED = 'SCHEDULED';
    case LEASED = 'LEASED';
    case PUBLISHING = 'PUBLISHING';
    case AWAITING_USER = 'AWAITING_USER';
    case RETRY_WAIT = 'RETRY_WAIT';
    case UNCERTAIN = 'UNCERTAIN';
    case PUBLISHED = 'PUBLISHED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
