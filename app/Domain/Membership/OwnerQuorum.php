<?php

namespace App\Domain\Membership;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class OwnerQuorum
{
    public static function ensureSurvives(bool $currentlyOwner, bool $losingOwner, int $activeOwners): void
    {
        if ($currentlyOwner && $losingOwner && $activeOwners <= 1) {
            throw new HttpException(409, 'The last active owner cannot be removed or demoted.');
        }
    }
}
