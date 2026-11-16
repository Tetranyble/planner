<?php

namespace Tests\Feature;

use App\Domain\Publishing\LiveContracts\LaravelTextHttpTransport;
use App\Domain\Publishing\LiveContracts\TextPostRequestCompiler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PublishingTextHttpTransportTest extends TestCase
{
    public function test_uses_only_pinned_endpoint_and_bearer_header_and_normalizes_receipt(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '123_456', 'token' => 'never-return'], 200)]);
        $request = TextPostRequestCompiler::compile('facebook', '1234567', 'Original text');
        $reply = (new LaravelTextHttpTransport)->send($request, 'synthetic-bearer');
        self::assertSame(200, $reply->status);
        self::assertSame('123_456', $reply->body['id']);
        self::assertArrayNotHasKey('token', $reply->body);
        Http::assertSent(function ($sent) {
            return $sent->url() === 'https://graph.facebook.com/v27.0/1234567/feed'
                && $sent->hasHeader('Authorization', 'Bearer synthetic-bearer')
                && $sent['message'] === 'Original text';
        });
    }

    public function test_invalid_bearer_is_rejected_without_http(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->expectException(\InvalidArgumentException::class);
        (new LaravelTextHttpTransport)->send(TextPostRequestCompiler::compile('x', '1234567', 'Hello'), "bad\r\nvalue");
    }
}
