<?php

namespace App\Authorization;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Single canonical brand-scope permission gateway for API, jobs, UTCP and MCP. */
final class BrandAuthorizer
{
    public function __construct(private readonly BrandPermissionGuard $permissions) {}

    public function authorize(User $user, Brand $brand, string $permission): void
    {
        if (! $this->permissions->allows($user, $brand, $permission)) {
            throw new AuthorizationException('You do not have access to this brand or operation.');
        }
    }
}
