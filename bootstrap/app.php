<?php

use App\Exceptions\ApiExceptionHandler;
use App\Exceptions\ExceptionReporter;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsurePlannerOAuthEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IsolatePlannerIntegrationBearer;
use App\Http\Middleware\RequirePlannerAgentGrant;
use App\Http\Middleware\RequirePlannerFirstPartySession;
use App\Http\Middleware\RequirePlannerIntegrationGrant;
use App\Http\Middleware\RequirePlannerMcpGrant;
use App\Http\Middleware\RequirePlannerOAuthAgentGrant;
use App\Http\Middleware\ResolveOrganisationContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Modules\Unipotence\Infrastructure\Exceptions\UnipotenceExceptionHandler;
use Modules\Unipotence\Infrastructure\Http\Middleware\ReconcileUnipotenceMiddleware;
use Modules\Unipotence\Infrastructure\Http\Middleware\UnipotenceGateMiddleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // All API endpoints, including legacy mutation routes, deny external read-only tokens.
        $middleware->api(append: [IsolatePlannerIntegrationBearer::class]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'organisation.context' => ResolveOrganisationContext::class,
            'planner.integration' => RequirePlannerIntegrationGrant::class,
            'planner.mcp' => RequirePlannerMcpGrant::class,
            'planner.agent' => RequirePlannerAgentGrant::class,
            'planner.oauth' => EnsurePlannerOAuthEnabled::class,
            'planner.oauth.agent' => RequirePlannerOAuthAgentGrant::class,
            'planner.first_party' => RequirePlannerFirstPartySession::class,
            'api.unipotent' => UnipotenceGateMiddleware::class,
            'unipotence.reconcile' => ReconcileUnipotenceMiddleware::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        * Sentry owns Sentry reporting.
        */
        Integration::handles($exceptions);

        /*
         * Custom reporting:
         * - Slack
         *
         * Laravel will STILL write to its normal log channel.
         */
        app(ExceptionReporter::class)->register($exceptions);

        app(UnipotenceExceptionHandler::class)->register($exceptions);

        /*
         * Consistent API responses.
         */
        app(ApiExceptionHandler::class)->register($exceptions);
        //        $exceptions->shouldRenderJsonWhen(
        //            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        //        );
    })->create();
