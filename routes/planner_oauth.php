<?php

use App\Http\Controllers\OAuth\ApprovePlannerOAuthConsentController;
use App\Http\Controllers\OAuth\DenyPlannerOAuthAuthorizationController;
use App\Http\Controllers\OAuth\IssuePlannerOAuthTokenController;
use App\Http\Controllers\OAuth\ListPlannerOAuthLinksController;
use App\Http\Controllers\OAuth\PlannerOAuthClientRegistrationController;
use App\Http\Controllers\OAuth\PlannerOAuthMetadataController;
use App\Http\Controllers\OAuth\RevokePlannerOAuthLinkController;
use App\Http\Controllers\OAuth\ShowPlannerOAuthAuthorizationController;
use App\Http\Middleware\RequirePlannerOAuthAuthorizationRequest;
use Illuminate\Support\Facades\Route;

/**
 * Entire OAuth HTTP surface is in this application-owned route file.
 * Passport::ignoreRoutes() prevents the package from registering any implicit routes.
 * The maintained Passport controllers are used solely for cryptographic/code exchange.
 */
Route::get('/.well-known/oauth-protected-resource', PlannerOAuthMetadataController::class)
    ->name('mcp.oauth.protected-resource');
Route::get('/.well-known/oauth-protected-resource/{path}', PlannerOAuthMetadataController::class)
    ->where('path', '.*')->name('mcp.oauth.protected-resource.nested');
Route::get('/.well-known/oauth-authorization-server', PlannerOAuthMetadataController::class)
    ->name('mcp.oauth.authorization-server');
Route::get('/.well-known/oauth-authorization-server/{path}', PlannerOAuthMetadataController::class)
    ->where('path', '.*')->name('mcp.oauth.authorization-server.nested');

Route::prefix('oauth')->name('passport.')->middleware('planner.oauth')->group(function (): void {
    Route::get('authorize', ShowPlannerOAuthAuthorizationController::class)
        ->middleware(['web', RequirePlannerOAuthAuthorizationRequest::class, 'throttle:30,1'])->name('authorizations.authorize');
    Route::post('authorize', ApprovePlannerOAuthConsentController::class)
        ->middleware(['web', 'auth', 'verified', 'throttle:15,1'])->name('authorizations.approve');
    Route::delete('authorize', DenyPlannerOAuthAuthorizationController::class)
        ->middleware(['web', 'auth', 'throttle:15,1'])->name('authorizations.deny');
    Route::post('token', IssuePlannerOAuthTokenController::class)
        ->middleware('throttle:30,1')->name('token');
    Route::post('register', PlannerOAuthClientRegistrationController::class)
        ->middleware('throttle:6,1')->name('mcp.registration');
});

// First-party user controls: revoke BOTH access and refresh tokens when disconnecting a client.
Route::middleware(['web', 'auth', 'verified', 'planner.oauth'])->group(function (): void {
    Route::get('/oauth/links', ListPlannerOAuthLinksController::class)
        ->name('planner.oauth.links.index');
    Route::delete('/oauth/links/{linkId}', RevokePlannerOAuthLinkController::class)
        ->whereNumber('linkId')->name('planner.oauth.links.destroy');
});
