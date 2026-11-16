<?php

require_once __DIR__.'/../../app/Domain/Publishing/Delivery/DispatchOutcome.php';
require_once __DIR__.'/../../app/Domain/Publishing/Delivery/ProviderFailureClassifier.php';
foreach (['PreparedProviderRequest', 'ProviderReceiptInterpreter', 'ProviderTextEgressPolicy', 'TextDispatchAuthorization', 'TextHttpResponse', 'TextHttpTransportContract', 'GuardedTextDispatch', 'TextPostRequestCompiler'] as $c) {
    require_once __DIR__.'/../../app/Domain/Publishing/LiveContracts/'.$c.'.php';
}
use App\Domain\Publishing\LiveContracts\GuardedTextDispatch;
use App\Domain\Publishing\LiveContracts\PreparedProviderRequest;
use App\Domain\Publishing\LiveContracts\ProviderTextEgressPolicy;
use App\Domain\Publishing\LiveContracts\TextDispatchAuthorization;
use App\Domain\Publishing\LiveContracts\TextHttpResponse;
use App\Domain\Publishing\LiveContracts\TextHttpTransportContract;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler;

$assertions = 0;
$check = function (bool $ok) use (&$assertions): void {
    $assertions++;
    if (! $ok) {
        throw new RuntimeException('Assertion '.$assertions.' failed');
    }
};
$fake = new class implements TextHttpTransportContract
{
    public int $calls = 0;

    public ?TextHttpResponse $reply = null;

    public function send(PreparedProviderRequest $request, string $bearerToken): TextHttpResponse
    {
        $this->calls++;
        if ($bearerToken !== 'synthetic') {
            throw new RuntimeException('Unexpected credential');
        }

        return $this->reply ?? new TextHttpResponse(null, [], [], true);
    }
};
$guard = new GuardedTextDispatch($fake);
$all = function (bool $enabled): TextDispatchAuthorization {
    return new TextDispatchAuthorization($enabled, true, true, true, true, true, true, true, true, true);
};
$specs = [
    TextPostRequestCompiler::compile('facebook', '1234567', 'Example post'),
    TextPostRequestCompiler::compile('linkedin', 'urn:li:organization:1234567', 'Example post'),
    TextPostRequestCompiler::compile('x', '1234567', 'Example post'),
];
foreach ($specs as $spec) {
    ProviderTextEgressPolicy::assertAllowed($spec);
    $assertions++;
    $out = $guard->execute($spec, $all(false), 'synthetic');
    $check($out->code === 'DISPATCH_GUARD_BLOCKED');
    $check($fake->calls === 0);
}
$invalid = [
    new PreparedProviderRequest('facebook', 'POST', 'https://graph.facebook.com.evil.test/v27.0/123/feed', [], [], 'body.id'),
    new PreparedProviderRequest('facebook', 'POST', 'https://graph.facebook.com/v27.0/123/feed?foo=bar', [], [], 'body.id'),
    new PreparedProviderRequest('facebook', 'POST', 'https://graph.facebook.com:443/v27.0/123/feed', [], [], 'body.id'),
    new PreparedProviderRequest('x', 'POST', 'https://api.x.com/2/users', [], [], 'body.data.id'),
];
foreach ($invalid as $spec) {
    try {
        ProviderTextEgressPolicy::assertAllowed($spec);
        throw new RuntimeException('Accepted invalid host');
    } catch (DomainException $e) {
        $check(true);
    }
}
foreach ($specs as $spec) {
    $fake->reply = match ($spec->provider) {
        'facebook' => new TextHttpResponse(200, ['id' => '123_456'], [], true),
        'linkedin' => new TextHttpResponse(201, [], ['x-restli-id' => 'urn:li:share:123'], true),
        'x' => new TextHttpResponse(201, ['data' => ['id' => '123456']], [], true),
    };
    $out = $guard->execute($spec, $all(true), 'synthetic');
    $check($out->kind === 'SUCCESS');
    $check($out->providerReceipt !== null);
}
$fake->reply = new TextHttpResponse(null, [], [], true);
$check($guard->execute($specs[0], $all(true), 'synthetic')->kind === 'UNCERTAIN');
$fake->reply = new TextHttpResponse(302, [], [], true);
$check($guard->execute($specs[0], $all(true), 'synthetic')->kind === 'UNCERTAIN');
$fake->reply = new TextHttpResponse(200,[],[],true);
$check($guard->execute($specs[0],$all(true),'synthetic')->kind === 'UNCERTAIN');
echo "Guarded text dispatch: $assertions assertions PASS\n";
