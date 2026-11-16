<?php

require dirname(__DIR__, 2).'/app/Domain/Publishing/Delivery/DispatchOutcome.php';
require dirname(__DIR__, 2).'/app/Domain/Publishing/Delivery/ProviderFailureClassifier.php';
require dirname(__DIR__, 2).'/app/Domain/Publishing/Delivery/RetrySafetyPolicy.php';
require dirname(__DIR__, 2).'/app/Domain/Publishing/LiveContracts/PreparedProviderRequest.php';
require dirname(__DIR__, 2).'/app/Domain/Publishing/LiveContracts/TextPostRequestCompiler.php';
require dirname(__DIR__, 2).'/app/Domain/Publishing/LiveContracts/ProviderReceiptInterpreter.php';
require dirname(__DIR__, 2).'/app/Domain/Publishing/LiveContracts/ProviderTextContractHarness.php';
use App\Domain\Publishing\LiveContracts\ProviderReceiptInterpreter as R;
use App\Domain\Publishing\LiveContracts\ProviderTextContractHarness;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler as C;

$checks = 0;
function verify(bool $p, string $m): void
{
    global $checks;
    $checks++;
    if (! $p) {
        throw new RuntimeException($m);
    }
}
function rejects(callable $fn): bool
{
    try {
        $fn();

        return false;
    } catch (InvalidArgumentException|DomainException $e) {
        return true;
    }
}
$cases = [['facebook', '123456', 'https://graph.facebook.com/v27.0/123456/feed'], ['linkedin', 'urn:li:organization:12345', 'https://api.linkedin.com/rest/posts'], ['x', '123456', 'https://api.x.com/2/tweets']];
foreach ($cases as [$p,$id,$url]) {
    $r = C::compile($p, $id, 'Example post text');
    verify($r->url === $url, $p.' endpoint');
    verify($r->method === 'POST', $p.' method');
    verify(! isset($r->headers['Authorization']), $p.' credential isolation');
    verify($r->metadata()['dispatch_allowed'] === false, $p.' gate');
    verify($r->metadata()['host'] === parse_url($url, PHP_URL_HOST), $p.' host');
    verify(rejects(fn () => C::compile($p, 'https://evil.example/a', 'text')), $p.' invalid actor');
    verify(rejects(fn () => C::compile($p, $id, ' ')), $p.' empty content');
    verify(rejects(fn () => C::compile($p, $id, str_repeat('x', 15001))), $p.' size');
    verify(R::interpret($p, 201)->kind === 'UNCERTAIN', $p.' missing receipt');
    verify(R::interpret($p, 429)->kind === 'RATE_LIMITED', $p.' rate limit');
    verify(R::interpret($p, null)->kind === 'UNCERTAIN', $p.' timeout');
}
verify(R::interpret('facebook', 200, ['id' => '123_456'])->kind === 'SUCCESS', 'FB receipt');
verify(R::interpret('x', 201, ['data' => ['id' => '123456']])->kind === 'SUCCESS', 'X receipt');
verify(R::interpret('linkedin', 201, [], ['X-RestLi-Id' => 'urn:li:share:123456'])->kind === 'SUCCESS', 'LI receipt');
verify(R::interpret('linkedin', 201, [], ['X-RestLi-Id' => "bad\nheader"])->kind === 'UNCERTAIN', 'reject malformed receipt');
foreach (['instagram', 'tiktok', 'youtube'] as $p) {
    verify(rejects(fn () => C::compile($p, '123', 'text')), $p.' cannot use text adapter');
}
foreach ($cases as [$provider,$id]) {
    foreach (ProviderTextContractHarness::SCENARIOS as $scenario) {
        $result = ProviderTextContractHarness::exercise($provider, $id, $scenario);
        verify($result['network_requests'] === 0 && $result['database_writes'] === 0, 'no side effects');
        verify($result['request']['dispatch_allowed'] === false, 'all requests held');
        verify($result['result']['kind'] === ($scenario === 'confirmed' ? 'SUCCESS' : match ($scenario) {
            'missing_receipt','timeout','server_failure' => 'UNCERTAIN','rate_limited' => 'RATE_LIMITED','permission_denied' => 'PERMANENT_FAILURE'
        }), 'classified fixture');
        verify($result['retry_decision']['action'] !== 'RETRY_LATER','no unverified idempotency retry');
    }
}
echo "{$checks} provider contract assertions passed; no network or database calls.\n";
