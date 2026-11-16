<?php

namespace App\Enums;

use App\Concerns\EnumOperation;
use DateTimeInterface;

enum DateFormat: string
{
    use EnumOperation;

    case DMY_SLASH = 'd/m/Y';   // 11/11/2026 (DD/MM/YYYY)
    case MDY_SLASH = 'm/d/Y';   // 11/11/2026 (MM/DD/YYYY)
    case YMD_ISO = 'Y-m-d';   // 2026-11-11 (YYYY-MM-DD)
    case DMY_DOT = 'd.m.Y';   // 11.11.2026 (DD.MM.YYYY)
    case YMD_COMPACT = 'Ymd';     // 20261111   (YYYYMMDD)
    case DMY_TEXT_DASH = 'd-M-Y';   // 11-Nov-2026 (DD-MMM-YYYY)
    case MDY_LONG = 'F j, Y';  // November 11, 2026 (MMMM D, YYYY)
    case DMY_LONG = 'j F Y';   // 11 November 2026 (D MMMM YYYY)
    case DMY_SHORT = 'd/m/y';   // 11/11/26   (DD/MM/YY)
    case MDY_SHORT = 'm/d/y';   // 11/11/26   (MM/DD/YY)

    case DMY_MEDIUM = 'j M Y';   // 11 Nov 2026 (en-NG Medium)

    /**
     * Helper method to format a DateTimeInterface directly.
     */
    public function format(DateTimeInterface $date): string
    {
        return $date->format($this->value);
    }
}
