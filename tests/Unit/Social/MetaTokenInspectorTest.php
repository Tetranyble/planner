<?php

namespace Tests\Unit\Social;

use App\Social\Data\MetaTokenInspection;
use App\Social\Providers\MetaAccessTokenInspector;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class MetaTokenInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('social_integrations.providers.facebook.client_id', 'synthetic-meta-app');
        config()->set('social_integrations.providers.facebook.client_secret', 'synthetic-meta-secret');
        config()->set('social_integrations.meta_graph_version', 'v27.0');
    }

    public function test_debug_token_uses_fixed_host_and_app_authorization(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            'is_valid' => true, 'app_id' => 'synthetic-meta-app', 'user_id' => 'actor-123',
            'scopes' => ['pages_show_list', 'pages_read_engagement'],
            'granular_scopes' => [['scope' => 'pages_read_engagement', 'target_ids' => ['page-1']]],
        ]])]);
        Http::preventStrayRequests();
        $result = app(MetaAccessTokenInspector::class)->inspect('synthetic-user-token');
        $this->assertTrue($result->valid);
        $this->assertSame('actor-123', $result->userId);
        $this->assertTrue($result->allows('pages_read_engagement', 'page-1'));
        $this->assertFalse($result->allows('pages_read_engagement', 'page-2'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com/v27.0/debug_token')
            && $request['input_token'] === 'synthetic-user-token'
            && $request->hasHeader('Authorization', 'Bearer synthetic-meta-app|synthetic-meta-secret'));
    }

    public function test_wrong_app_is_invalid_even_if_meta_says_valid(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => ['is_valid' => true, 'app_id' => 'someone-else', 'user_id' => 'actor']])]);
        $this->assertFalse(app(MetaAccessTokenInspector::class)->inspect('token')->valid);
    }

    public function test_malformed_provider_response_fails_closed(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => ['scopes' => []]])]);
        $this->expectException(RuntimeException::class);
        app(MetaAccessTokenInspector::class)->inspect('token');
    }

    public function test_unknown_granular_target_never_inherits_a_page_grant(): void
    {
        $result = new MetaTokenInspection(true, 'app', 'user', ['pages_read_engagement'],
            ['pages_read_engagement' => ['Page-A']], null, null);
        $this->assertFalse($result->allows('pages_read_engagement', 'Page-B'));
    }
}
