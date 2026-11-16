<?php

namespace App\Enums\Growth;

use App\Concerns\EnumOperation;

enum DemoBookingStatus: string
{
    use EnumOperation;

    case BOOKED = 'BOOKED';
    case ATTENDED = 'ATTENDED';
    case NO_SHOW = 'NO_SHOW';
    case CANCELLED = 'CANCELLED';
}
