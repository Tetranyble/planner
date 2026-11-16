<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum NotificationEventType: string
{
    use EnumOperation;
    case PUBLICATION_FAILED = 'PUBLICATION_FAILED';
    case APPROVAL_REQUESTED = 'APPROVAL_REQUESTED';
    case SOCIAL_ACCOUNT_DISCONNECTED = 'SOCIAL_ACCOUNT_DISCONNECTED';
    case CAMPAIGN_COMPLETED = 'CAMPAIGN_COMPLETED';
    case WEEKLY_REPORT_READY = 'WEEKLY_REPORT_READY';
}
