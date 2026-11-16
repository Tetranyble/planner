<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Passport;
use Laravel\Passport\PersonalAccessTokenFactory;
use Laravel\Passport\PersonalAccessTokenResult;

/**
 * Isolates Passport's OAuthenticatable contract from the normal Sanctum User.
 * Mixing both HasApiTokens traits into User would replace existing Sanctum token behaviour.
 */
final class PlannerOAuthUser extends User implements OAuthenticatable
{
    /** Same explicit allowlist as User; this OAuth principal shares the users table. */
    protected $fillable = ['name', 'email', 'password'];

    public function oauthApps(): MorphMany
    {
        return $this->morphMany(Passport::clientModel(), 'owner');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Passport::tokenModel(), 'user_id', $this->getAuthIdentifierName());
    }

    /** Passport-only principal; never used to issue a Sanctum grant. */
    public function createToken(string $name, array $scopes = [], ?DateTimeInterface $expiresAt = null): PersonalAccessTokenResult
    {
        return app(PersonalAccessTokenFactory::class)->make($this->getAuthIdentifier(), $name, $scopes, $this->getProviderName());
    }

    public function currentAccessToken(): ?ScopeAuthorizable
    {
        return $this->accessToken;
    }

    /** Parameter deliberately stays untyped to preserve compatibility with Sanctum's parent method. */
    public function withAccessToken($accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function tokenCan($scope): bool
    {
        return $this->accessToken !== null && $this->accessToken->can($scope);
    }

    public function tokenCant($scope): bool
    {
        return ! $this->tokenCan($scope);
    }

    public function getProviderName(): string
    {
        return 'oauth_users';
    }
}
