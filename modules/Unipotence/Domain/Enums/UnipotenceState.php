<?php

namespace Modules\Unipotence\Domain\Enums;

enum UnipotenceState: string
{
    case Claimed = 'claimed';
    case Completed = 'completed';
    case Indeterminate = 'indeterminate';
}
