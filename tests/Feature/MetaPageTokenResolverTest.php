<?php

namespace Tests\Feature;

use App\Services\Publishing\MetaPageTokenResolver;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

final class MetaPageTokenResolverTest extends TestCase
{
    public function test_resolves_exact_page_credential_with_content_task_only(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://graph.facebook.com/*' => Http::response([
            'data' => [['id' => '123450', 'name' => 'Other', 'access_token' => 'bad-page-token', 'tasks' => ['CREATE_CONTENT']],
                ['id' => '999900', 'access_token' => 'synthetic-page-token', 'tasks' => ['CREATE_CONTENT']]],
        ], 200)]);
        $token = app(MetaPageTokenResolver::class)->resolve('synthetic-user-token', '999900');
        $this->assertSame('synthetic-page-token', $token);
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => $req->method() === 'GET'
            && str_starts_with($req->url(), 'https://graph.facebook.com/v27.0/me/accounts?')
            && ! str_contains($req->url(), 'synthetic-user-token'));
    }

    public function test_missing_create_content_task_fails_closed(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://graph.facebook.com/*' => Http::response([
            'data' => [['id' => '999900', 'access_token' => 'synthetic-page-token', 'tasks' => ['ANALYZE']]],
        ], 200)]);
        $this->expectException(ConflictHttpException::class);
        app(MetaPageTokenResolver::class)->resolve('synthetic-user-token', '999900');
    }

    public function test_rejects_user_supplied_url_like_page_identity_before_http(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        try {
            app(MetaPageTokenResolver::class)->resolve('synthetic-token', '999900@evil.invalid');
            $this->fail('Must reject invalid page ID');
        } catch (ConflictHttpException) {
            Http::assertNothingSent();
        }
    }
}
