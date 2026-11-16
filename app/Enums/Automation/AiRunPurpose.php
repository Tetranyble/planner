<?php

namespace App\Enums\Automation;

use App\Concerns\EnumOperation;

enum AiRunPurpose: string
{
    use EnumOperation;

    case RESEARCH = 'RESEARCH';
    case DRAFT_CONTENT = 'DRAFT_CONTENT';
    case ANALYSE = 'ANALYSE';
    case REPORT = 'REPORT';
    case ASSISTANT_REQUEST = 'ASSISTANT_REQUEST';
}
