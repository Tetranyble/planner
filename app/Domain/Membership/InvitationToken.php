<?php

namespace App\Domain\Membership;

/** 256-bit bearer secret; only its SHA-256 digest is stored on the invitation row. */
final class InvitationToken
{
    public static function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hasValidFormat(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }

    public static function digest(string $token): string
    {
        return hash('sha256', $token);
    }
}
