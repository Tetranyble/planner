<?php

namespace Tests\Unit\Integrations;

use App\Http\Middleware\RequirePlannerIntegrationGrant;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\PlannerIntegrationGrantRepositoryContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class PlannerIntegrationTokenMiddlewareTest extends TestCase
{
    public function test_first_party_session_without_actual_token_is_rejected(): void
    {
        config(['planner.integration_read_enabled' => true]);
        $user = new User;
        $user->id = 42;
        $request = Request::create('/api/v1/integrations/test', 'GET');
        $request->setUserResolver(static fn () => $user);
        $repo = $this->createMock(PlannerIntegrationGrantRepositoryContract::class);
        $middleware = new RequirePlannerIntegrationGrant($repo);
        try {
            $middleware->handle($request, static fn () => new Response('unsafe'));
            self::fail('Session-based access should not reach third-party endpoints.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }

    public function test_token_cannot_cross_brand_even_when_scoped_to_same_user(): void
    {
        config(['planner.integration_read_enabled' => true]);
        $user = new User;
        $user->id = 42;
        $token = new PersonalAccessToken;
        $token->id = 987;
        $token->tokenable_id = 42;
        $token->tokenable_type = $user->getMorphClass();
        $token->abilities = ['planner:read'];
        $user->withAccessToken($token);
        $org = new Organisation;
        $org->id = 1;
        $brand = new Brand;
        $brand->id = 4;
        $brand->organisation_id = 1;
        $request = Request::create('/api/v1/integrations/test', 'GET');
        $request->setUserResolver(static fn () => $user);
        $route = new Route('GET', '/api/v1/integrations/test', static fn () => null);
        $route->bind($request);
        $route->setParameter('organisation', $org);
        $route->setParameter('brand', $brand);
        $request->setRouteResolver(static fn () => $route);
        $repo = $this->createMock(PlannerIntegrationGrantRepositoryContract::class);
        $repo->expects(self::once())->method('allows')->with(987, $org, $brand, $user)->willReturn(false);
        try {
            (new RequirePlannerIntegrationGrant($repo))->handle($request, static fn () => new Response('unsafe'));
            self::fail('Cross-brand bearer token must not gain access.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
    }
}
