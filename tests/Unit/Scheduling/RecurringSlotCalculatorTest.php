<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\RecurringSlotCalculator as Slots;
use PHPUnit\Framework\TestCase;

final class RecurringSlotCalculatorTest extends TestCase
{
    public function test_weekly_byday_and_interval_produce_deterministic_utc_slots(): void
    {
        $args = [12, 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE', 'Africa/Lagos',
            '2026-12-07 10:00:00', '2026-12-07T00:00:00Z', '2026-12-20T00:00:00Z', 20];
        $result = Slots::expand(...$args);
        self::assertSame(4, count($result['slots']));
        self::assertSame('2026-12-07T09:00:00Z', $result['slots'][0]['run_at']);
        self::assertSame($result, Slots::expand(...$args));
        self::assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-5[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $result['slots'][0]['occurrence_key']);
    }

    public function test_nonexistent_spring_clock_and_ambiguous_fall_clock_are_skipped(): void
    {
        $spring = Slots::expand(1, 'FREQ=DAILY;INTERVAL=1', 'America/New_York',
            '2026-03-07 02:30:00', '2026-03-07T00:00:00Z', '2026-03-12T00:00:00Z', 3);
        self::assertSame(1, $spring['skipped_dst']);
        self::assertCount(3, $spring['slots']);
        $fall = Slots::expand(2, 'FREQ=DAILY;INTERVAL=1', 'America/New_York',
            '2026-10-31 01:30:00', '2026-10-31T00:00:00Z', '2026-11-04T00:00:00Z', 3);
        self::assertSame(1, $fall['skipped_dst']);
        self::assertCount(3, $fall['slots']);
    }

    public function test_count_is_global_even_when_preview_starts_later(): void
    {
        $result = Slots::expand(12, 'FREQ=DAILY;INTERVAL=1', 'UTC',
            '2026-12-01 12:00:00', '2026-12-03T00:00:00Z', '2026-12-10T00:00:00Z', 3);
        self::assertCount(1, $result['slots']);
        self::assertSame('2026-12-03T12:00:00Z', $result['slots'][0]['run_at']);
    }

    public function test_unsupported_recurrence_fields_fail_closed(): void
    {
        foreach (['FREQ=MINUTELY', 'FREQ=DAILY;COUNT=100', 'FREQ=WEEKLY;BYDAY=ZZ',
            'FREQ=DAILY;BYDAY=MO', 'FREQ=DAILY;INTERVAL=0', 'FREQ=DAILY;FREQ=WEEKLY'] as $rule) {
            try {
                Slots::parse($rule, 1);
                self::fail($rule.' should fail.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
