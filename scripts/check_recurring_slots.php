<?php

/** Standalone contract exercise: php scripts/check_recurring_slots.php */
require __DIR__.'/../app/Domain/Scheduling/RecurringSlotCalculator.php';
use App\Domain\Scheduling\RecurringSlotCalculator as Slots;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};
$a = Slots::expand(12, 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE', 'Africa/Lagos',
    '2026-12-07 10:00:00', '2026-12-07T00:00:00Z', '2026-12-20T00:00:00Z', 20);
$assert(count($a['slots']) === 4, 'weekly number');
$assert($a['slots'][0]['run_at'] === '2026-12-07T09:00:00Z', 'Lagos UTC offset');
$assert($a === Slots::expand(12, 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE', 'Africa/Lagos',
    '2026-12-07 10:00:00', '2026-12-07T00:00:00Z', '2026-12-20T00:00:00Z', 20), 'stable retry');
$assert((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a['slots'][0]['occurrence_key']), 'stable UUIDv5');
$b = Slots::expand(1, 'FREQ=DAILY', 'America/New_York',
    '2026-03-07 02:30:00', '2026-03-07T00:00:00Z', '2026-03-12T00:00:00Z', 3);
$assert($b['skipped_dst'] === 1, 'spring DST gap');
$assert(count($b['slots']) === 3, 'spring occurrences');
$c = Slots::expand(2, 'FREQ=DAILY', 'America/New_York',
    '2026-10-31 01:30:00', '2026-10-31T00:00:00Z', '2026-11-04T00:00:00Z', 3);
$assert($c['skipped_dst'] === 1, 'fall ambiguous clock');
$assert(count($c['slots']) === 3, 'fall occurrences');
$d = Slots::expand(13, 'FREQ=DAILY', 'UTC',
    '2026-12-01 12:00:00', '2026-12-03T00:00:00Z', '2026-12-10T00:00:00Z', 3);
$assert(count($d['slots']) === 1, 'global maximum before preview');
$assert($d['slots'][0]['run_at'] === '2026-12-03T12:00:00Z', 'window correct');
foreach (['FREQ=MINUTELY', 'FREQ=DAILY;COUNT=99', 'FREQ=DAILY;BYDAY=MO', 'FREQ=DAILY;INTERVAL=0', 'FREQ=WEEKLY;BYDAY=XX', 'FREQ=DAILY;FREQ=WEEKLY'] as $invalid) {
    $threw = false;
    try {
        Slots::parse($invalid, 1);
    } catch (InvalidArgumentException) {
        $threw = true;
    }
    $assert($threw, 'reject '.$invalid);
}
echo "{$checks}/{$checks} recurrence assertions passed\n";
