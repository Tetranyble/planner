<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum ConsentSourceKind: string
{
    use EnumOperation;

    case FORM = 'FORM';
    case EMAIL = 'EMAIL';
    case VERBAL = 'VERBAL';
    case IMPORT_REVIEW = 'IMPORT_REVIEW';
    case PROVIDER = 'PROVIDER';
}
