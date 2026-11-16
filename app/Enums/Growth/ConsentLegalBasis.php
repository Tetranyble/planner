<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum ConsentLegalBasis: string
{
    use EnumOperation;

    case CONSENT = 'CONSENT';
    case LEGITIMATE_INTEREST = 'LEGITIMATE_INTEREST';
    case CONTRACT = 'CONTRACT';
    case LEGAL_OBLIGATION = 'LEGAL_OBLIGATION';
}
