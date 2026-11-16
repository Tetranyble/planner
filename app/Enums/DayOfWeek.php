<?php

namespace App\Enums;

use App\Concerns\EnumOperation;
use DateTimeInterface;
use IntlDateFormatter;

enum DayOfWeek: int
{
    use EnumOperation;

    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;
    case SATURDAY = 6;
    case SUNDAY = 0;

    /**
     * Create a DayOfWeek instance from a DateTimeInterface object.
     */
    public static function fromDate(DateTimeInterface $date): self
    {
        return self::from((int) $date->format('N'));
    }

    /**
     * Convert to 0-based Sunday index (0 = Sunday ... 6 = Saturday) for date('w').
     */
    public function toZeroBasedSunday(): int
    {
        return $this === self::SUNDAY ? 0 : $this->value;
    }

    /**
     * Check if the day falls on a weekend.
     */
    public function isWeekend(): bool
    {
        return $this === self::SATURDAY || $this === self::SUNDAY;
    }

    /**
     * Get the localized day name (e.g., "Wednesday" or "Ọjọ́rú" in yo-NG).
     */
    public function label(Locale $locale = Locale::EN_NG, bool $short = false): string
    {
        // Reference Monday: 2024-01-01 was a Monday
        $dayOffset = $this->value - 1;
        $date = new \DateTimeImmutable("2024-01-01 +{$dayOffset} days");
        $pattern = $short ? 'EEE' : 'EEEE';

        $formatter = new IntlDateFormatter(
            $locale->value,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            null,
            $pattern
        );

        return (string) $formatter->format($date);
    }
}
