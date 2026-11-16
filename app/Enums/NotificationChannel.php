<?php

namespace App\Enums;

use App\Concerns\EnumOperation;

enum NotificationChannel: string
{
    use EnumOperation;

    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case PUSH = 'PUSH';
    case SLACK = 'SLACK';

}
