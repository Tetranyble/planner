<?php

require_once __DIR__.'/../../app/Domain/Analytics/ProviderPerformanceSignals.php';
use App\Domain\Analytics\ProviderPerformanceSignals as P;

$n = 0;
function checkSignal(bool $value, string $message): void
{
    global $n;
    $n++;
    if (! $value) {
        fwrite(STDERR, "FAILED: $message\n");
        exit(1);
    }
}
checkSignal(P::evaluate([])['status'] === 'INSUFFICIENT_BASELINES', 'none');
checkSignal(P::evaluate([[10, 20], [10, 19]])['views'] === null, 'small sample withheld');
$trend = P::evaluate([[10, 20], [10, 25], [10, 30]]);
checkSignal($trend['status'] === 'INCREASED', 'increased');
checkSignal($trend['views']['baseline'] === 30, 'baseline');
checkSignal($trend['views']['latest'] === 75, 'latest');
checkSignal($trend['change_percent'] === 150.0, 'percent');
checkSignal(P::evaluate([[10, 8], [10, 8], [10, 8]])['status'] === 'DECREASED', 'decrease');
checkSignal(P::evaluate([[10, 10], [10, 11], [10, 9]])['status'] === 'WITHIN_TEN_PERCENT', 'stable');
checkSignal(P::evaluate([[0, 1], [0, 2], [0, 3]])['change_percent'] === null, 'zero baseline');
checkSignal(P::evaluate([[10, 20], [10, 20], [10, 20]], true)['views'] === null, 'truncation hides trend');
foreach ([[[-1, 2], [3, 4], [5, 6]], [[null, 10], [2, 2], [3, 3]]] as $bad) {
    try {
        P::evaluate($bad);
        checkSignal(false, 'invalid pair allowed');
    } catch (DomainException) {
        checkSignal(true, 'invalid pair rejected');
    }
}
echo "Provider performance signals $n assertions passed.\n";
