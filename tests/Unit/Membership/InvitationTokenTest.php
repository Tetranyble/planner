<?php

namespace Tests\Unit\Membership;

use App\Domain\Membership\InvitationToken;
use PHPUnit\Framework\TestCase;

final class InvitationTokenTest extends TestCase
{
    public function test_token_has_256_bits_of_entropy_and_stable_digest(): void
    {
        $first = InvitationToken::issue();
        $second = InvitationToken::issue();
        $this->assertTrue(InvitationToken::hasValidFormat($first));
        $this->assertSame(64, strlen($first));
        $this->assertNotSame($first, $second);
        $this->assertNotSame($first, InvitationToken::digest($first));
        $this->assertSame(InvitationToken::digest($first), InvitationToken::digest($first));
    }

    public function test_malformed_or_short_tokens_are_rejected(): void
    {
        foreach (['', 'example', str_repeat('0', 63), str_repeat('Z', 64), str_repeat('g', 64)] as $invalid) {
            $this->assertFalse(InvitationToken::hasValidFormat($invalid));
        }
    }
}
