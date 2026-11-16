<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum CampaignStatus: string
{
    use EnumOperation;

    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case PAUSED = 'PAUSED';
    case COMPLETED = 'COMPLETED';
    case ARCHIVED = 'ARCHIVED';
}
