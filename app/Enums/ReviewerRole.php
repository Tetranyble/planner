<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum ReviewerRole: string
{
    use EnumOperation;

    case ADMIN = 'ADMIN';
    case PUBLISHER = 'PUBLISHER';
    case EDITOR = 'EDITOR';
}
