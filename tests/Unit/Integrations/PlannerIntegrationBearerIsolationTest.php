<?php

namespace Tests\Unit\Integrations;

use App\Http\Middleware\IsolatePlannerIntegrationBearer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PlannerIntegrationBearerIsolationTest extends TestCase
{
    private function request(string $method, string $url): Request
    {
        return Request::create($url, $method, [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer test']);
    }

    private function actingReadOnlyUser(): void
    {
        $user = new User;
        $user->id = 13;
        $token = new PersonalAccessToken;
        $token->abilities = ['planner:read'];
        $user->withAccessToken($token);
        Auth::shouldReceive('guard')->once()->with('sanctum')->andReturn(new class($user)
        {
            public function __construct(private User $user) {}

            public function user(): User
            {
                return $this->user;
            }
        });
    }

    public function test_scoped_read_token_cannot_reach_any_legacy_application_api(): void
    {
        $this->actingReadOnlyUser();
        try {
            (new IsolatePlannerIntegrationBearer)->handle(
                $this->request('POST', '/api/v1/organisations/aaa/brands/bbb/content'),
                static fn () => new Response('unsafe'));
            self::fail('A reporting token must not create content.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    public function test_scoped_read_token_cannot_read_unrelated_legacy_endpoints(): void
    {
        $this->actingReadOnlyUser();
        try {
            (new IsolatePlannerIntegrationBearer)->handle(
                $this->request('GET', '/api/v1/organisations'),
                static fn () => new Response('unsafe'));
            self::fail('A scoped integration token must be confined to its own API namespace.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    public function test_scoped_read_token_may_only_reach_dedicated_get_endpoints(): void
    {
        $this->actingReadOnlyUser();
        $r = (new IsolatePlannerIntegrationBearer)->handle(
            $this->request('GET', '/api/v1/integrations/organisations/org/brands/brand/utcp'),
            static fn () => new Response('read'));
        self::assertSame('read', $r->getContent());
    }

    public function test_scoped_read_token_cannot_post_even_to_integration_prefix(): void
    {
        $this->actingReadOnlyUser();
        try {
            (new IsolatePlannerIntegrationBearer)->handle(
                $this->request('POST', '/api/v1/integrations/organisations/org/brands/brand/utcp'),
                static fn () => new Response('unsafe'));
            self::fail('A read token must not POST.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }
}
