<?php

namespace App\Enums\Operation;

use App\Concerns\EnumOperation;

enum NotificationPreferenceChannel: string
{
    use EnumOperation;

    case IN_APP = 'IN_APP';
    case EMAIL = 'EMAIL';
}
