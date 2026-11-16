<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum LeadContactKind: string
{
    use EnumOperation;

    case PERSON = 'PERSON';
    case INSTITUTIONAL_MAILBOX = 'INSTITUTIONAL_MAILBOX';
}
