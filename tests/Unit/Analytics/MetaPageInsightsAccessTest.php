<?php

namespace Tests\Unit\Analytics;

use App\Services\Publishing\MetaPageTokenResolver;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class MetaPageInsightsAccessTest extends TestCase
{
    public function test_insights_read_accepts_analyze_grant_without_content_creation(): void
    {
        Http::preventStrayRequests();
        config()->set('social_integrations.meta_graph_version', 'v27.0');
        Http::fake(['graph.facebook.com/v27.0/me/accounts*' => Http::response([
            'data' => [['id' => '123456789', 'access_token' => 'ephemeral-page-token', 'tasks' => ['ANALYZE']]],
        ], 200)]);
        self::assertSame('ephemeral-page-token', (new MetaPageTokenResolver)->resolve('synthetic-user-token', '123456789', true));
        Http::assertSentCount(1);
    }

    public function test_insights_read_refuses_only_content_creation_grant(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'data' => [['id' => '123456789', 'access_token' => 'temporary-page-token', 'tasks' => ['CREATE_CONTENT']]],
        ], 200)]);
        $this->expectException(ConflictHttpException::class);
        (new MetaPageTokenResolver)->resolve('synthetic-user-token', '123456789', true);
    }

    public function test_publishing_grant_does_not_inherit_insights_only_permission(): void
    {
        Http::preventStrayRequests();
        Http::fake(['graph.facebook.com/*' => Http::response([
            'data' => [['id' => '123456789', 'access_token' => 'temporary-page-token', 'tasks' => ['ANALYZE']]],
        ], 200)]);
        $this->expectException(ConflictHttpException::class);
        (new MetaPageTokenResolver)->resolve('synthetic-user-token', '123456789');
    }
}
