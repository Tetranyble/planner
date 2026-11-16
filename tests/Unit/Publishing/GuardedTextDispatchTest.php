<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\LiveContracts\GuardedTextDispatch;
use App\Domain\Publishing\LiveContracts\PreparedProviderRequest;
use App\Domain\Publishing\LiveContracts\ProviderTextEgressPolicy;
use App\Domain\Publishing\LiveContracts\TextDispatchAuthorization;
use App\Domain\Publishing\LiveContracts\TextHttpResponse;
use App\Domain\Publishing\LiveContracts\TextHttpTransportContract;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler;
use PHPUnit\Framework\TestCase;

final class GuardedTextDispatchTest extends TestCase
{
    private function authorization(bool $enabled): TextDispatchAuthorization
    {
        return new TextDispatchAuthorization($enabled, true, true, true, true, true, true, true, true, true);
    }

    public function test_incomplete_gates_never_touch_transport(): void
    {
        $transport = new class implements TextHttpTransportContract
        {
            public int $calls = 0;

            public function send(PreparedProviderRequest $request, string $bearerToken): TextHttpResponse
            {
                $this->calls++;
                throw new \RuntimeException('Unexpected network');
            }
        };
        $request = TextPostRequestCompiler::compile('x', '123456', 'Hello');
        $out = (new GuardedTextDispatch($transport))->execute($request, $this->authorization(false), 'test-token');
        self::assertSame('PERMANENT_FAILURE', $out->kind);
        self::assertSame(0, $transport->calls);
    }

    public function test_ambiguous_transport_result_is_never_a_success(): void
    {
        $transport = new class implements TextHttpTransportContract
        {
            public function send(PreparedProviderRequest $request, string $bearerToken): TextHttpResponse
            {
                return new TextHttpResponse(null, [], [], true);
            }
        };
        $request = TextPostRequestCompiler::compile('facebook', '123456', 'Hello');
        $out = (new GuardedTextDispatch($transport))->execute($request, $this->authorization(true), 'test-token');
        self::assertSame('UNCERTAIN', $out->kind);
    }

    public function test_host_confusion_is_rejected_before_transport(): void
    {
        $request = new PreparedProviderRequest('facebook', 'POST', 'https://graph.facebook.com.evil.invalid/v27.0/123/feed', [], [], 'body.id');
        $this->expectException(\DomainException::class);
        ProviderTextEgressPolicy::assertAllowed($request);
    }
}
