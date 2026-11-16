<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum OutreachSequencePurpose: string
{
    use EnumOperation;

    case DEMO_FOLLOWUP = 'DEMO_FOLLOWUP';
    case ONBOARDING = 'ONBOARDING';
    case MARKETING = 'MARKETING';
}
