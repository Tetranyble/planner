<?php

namespace App\Enums\Content;

use App\Concerns\EnumOperation;

enum CampaignObjective: string
{
    use EnumOperation;

    case AWARENESS = 'AWARENESS';
    case ENGAGEMENT = 'ENGAGEMENT';
    case TRAFFIC = 'TRAFFIC';
    case LEADS = 'LEADS';
    case DEMOS = 'DEMOS';
    case SUBSCRIPTIONS = 'SUBSCRIPTIONS';
    case RETENTION = 'RETENTION';
}
