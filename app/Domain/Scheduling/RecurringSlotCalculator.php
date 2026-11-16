<?php

namespace App\Domain\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Deterministic, deliberately bounded recurrence expansion. No automatic posting.
 * A v1 schedule supports DAILY/WEEKLY; local nonexistent or ambiguous DST instants
 * are skipped, rather than silently shifting a user's chosen wall-clock time.
 */
final class RecurringSlotCalculator
{
    private const DAYS = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

    /** @return array{frequency:string,interval:int,byday:list<int>} */
    public static function parse(string $rule, int $startWeekday): array
    {
        $pairs = [];
        foreach (explode(';', strtoupper(trim($rule))) as $part) {
            if (! preg_match('/^([A-Z]+)=([A-Z0-9,]+)$/D', $part, $match) || isset($pairs[$match[1]])) {
                throw new InvalidArgumentException('Invalid or duplicate RRULE field.');
            }
            $pairs[$match[1]] = $match[2];
        }
        foreach (array_keys($pairs) as $key) {
            if (! in_array($key, ['FREQ', 'INTERVAL', 'BYDAY'], true)) {
                throw new InvalidArgumentException('Only FREQ, INTERVAL and BYDAY are supported.');
            }
        }
        $frequency = $pairs['FREQ'] ?? '';
        if (! in_array($frequency, ['DAILY', 'WEEKLY'], true)) {
            throw new InvalidArgumentException('Schedule frequency must be DAILY or WEEKLY.');
        }
        $interval = (int) ($pairs['INTERVAL'] ?? '1');
        if (! preg_match('/^[1-9][0-9]?$/D', $pairs['INTERVAL'] ?? '1') || $interval > 30) {
            throw new InvalidArgumentException('Schedule interval must be between 1 and 30.');
        }
        if ($frequency === 'DAILY' && isset($pairs['BYDAY'])) {
            throw new InvalidArgumentException('BYDAY requires WEEKLY frequency.');
        }
        $byday = [$startWeekday];
        if (isset($pairs['BYDAY'])) {
            $names = explode(',', $pairs['BYDAY']);
            if (count($names) > 7 || count(array_unique($names)) !== count($names)) {
                throw new InvalidArgumentException('BYDAY must contain unique weekday values.');
            }
            $byday = [];
            foreach ($names as $day) {
                if (! isset(self::DAYS[$day])) {
                    throw new InvalidArgumentException('Invalid BYDAY weekday.');
                }
                $byday[] = self::DAYS[$day];
            }
            sort($byday);
        }

        return ['frequency' => $frequency, 'interval' => $interval, 'byday' => $byday];
    }

    /**
     * @return array{slots:list<array{local:string,run_at:string,occurrence_key:string}>,skipped_dst:int}
     */
    public static function expand(
        int $scheduleId, string $rrule, string $timezone, string $startLocal,
        string $fromUtc, string $toUtc, int $maximum, ?string $endAtUtc = null
    ): array {
        if ($scheduleId < 1 || $maximum < 1 || $maximum > 366) {
            throw new InvalidArgumentException('Invalid schedule or occurrence limit.');
        }
        $zone = new DateTimeZone($timezone);
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startLocal, new DateTimeZone('UTC'));
        if (! $wall || $wall->format('Y-m-d H:i:s') !== $startLocal) {
            throw new InvalidArgumentException('Start must be a real local YYYY-MM-DD HH:MM:SS value.');
        }
        $from = new DateTimeImmutable($fromUtc);
        $to = new DateTimeImmutable($toUtc);
        $end = $endAtUtc === null ? null : new DateTimeImmutable($endAtUtc);
        if ($to <= $from || $to->getTimestamp() - $from->getTimestamp() > 90 * 86400) {
            throw new InvalidArgumentException('Preview must cover no more than 90 calendar days.');
        }
        $rule = self::parse($rrule, (int) $wall->format('N'));
        $clock = $wall->format('H:i:s');
        $startDay = $wall->setTime(0, 0, 0);
        $startWeek = $startDay->modify('-'.((int) $startDay->format('N') - 1).' days');
        $slots = [];
        $seen = 0;
        $skippedDst = 0;
        // At most one year of recurrence, inclusive of the first day.
        for ($offset = 0; $offset <= 366 && $seen < $maximum; $offset++) {
            $day = $startDay->modify('+'.$offset.' days');
            $weekday = (int) $day->format('N');
            $weekNumber = intdiv((int) $startWeek->diff($day)->days, 7);
            $matches = $rule['frequency'] === 'DAILY'
                ? $offset % $rule['interval'] === 0
                : $weekNumber % $rule['interval'] === 0 && in_array($weekday, $rule['byday'], true);
            if (! $matches) {
                continue;
            }
            $local = $day->format('Y-m-d').' '.$clock;
            $utc = self::uniqueUtc($local, $zone);
            if ($utc === null) {
                $skippedDst++;

                continue;
            }
            if ($end && $utc > $end) {
                break;
            }
            $seen++;
            if ($utc < $from || $utc >= $to) {
                continue;
            }
            // UUIDv5 using the DNS namespace. Stable across requests and retries.
            $name = $scheduleId.'|'.$local.'|'.$utc->format('Y-m-d\TH:i:s\Z');
            $raw = sha1(hex2bin('6ba7b8109dad11d180b400c04fd430c8').$name, true);
            $hex = bin2hex($raw);
            $hex[12] = '5';
            $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
            $uuid = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4)
                .'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
            $slots[] = ['local' => $local, 'run_at' => $utc->format('Y-m-d\TH:i:s\Z'), 'occurrence_key' => $uuid];
        }

        return ['slots' => $slots, 'skipped_dst' => $skippedDst];
    }

    /** A local wall time must map to exactly one UTC instant. */
    private static function uniqueUtc(string $local, DateTimeZone $zone): ?DateTimeImmutable
    {
        $base = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $local, new DateTimeZone('UTC'));
        if (! $base) {
            return null;
        }
        $timestamp = $base->getTimestamp();
        $offsets = [];
        $transitions = $zone->getTransitions($timestamp - 172800, $timestamp + 172800);
        if (is_array($transitions)) {
            foreach ($transitions as $transition) {
                $offsets[] = (int) $transition['offset'];
            }
        } else {
            $offsets[] = $base->setTimezone($zone)->getOffset();
        }
        $valid = [];
        foreach (array_unique($offsets) as $offset) {
            $candidate = $base->setTimestamp($timestamp - $offset);
            if ($candidate->setTimezone($zone)->format('Y-m-d H:i:s') === $local) {
                $valid[$candidate->getTimestamp()] = $candidate;
            }
        }

        return count($valid) === 1 ? reset($valid) : null;
    }
}
